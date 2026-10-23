<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SketchWordCsvImporter
{
    public const HEADERS = ['word', 'aliases'];
    public const MAX_WORDS = 2000;
    public const MAX_BYTES = 2 * 1024 * 1024;

    public function import(Game $game, UploadedFile $file): int
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            $this->fail('Upload a valid UTF-8 CSV file no larger than 2 MB.');
        }
        $rows = $this->parse($file);

        return DB::transaction(function () use ($game, $rows): int {
            $locked = Game::query()->whereKey($game->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->game_type !== 'sketch') {
                $this->fail('Drawing words can only be imported into a Sketch & Guess game.');
            }
            $existing = $locked->words()->pluck('word')->all();
            $seen = array_fill_keys(array_map(self::normalize(...), $existing), true);
            $new = [];
            foreach ($rows as $row) {
                $key = self::normalize($row['word']);
                if (! isset($seen[$key])) {
                    $new[] = $row;
                    $seen[$key] = true;
                }
            }
            if (count($existing) + count($new) > self::MAX_WORDS) {
                $this->fail('A game can contain at most 2,000 drawing words. Nothing was imported.');
            }
            $position = (int) $locked->words()->reorder()->max('position');
            foreach ($new as $row) {
                $locked->words()->create($row + ['position' => ++$position, 'is_active' => true]);
            }

            return count($new);
        }, 3);
    }

    public static function normalize(string $value): string
    {
        return mb_strtolower(self::clean($value), 'UTF-8');
    }

    private static function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) $this->fail('The CSV file could not be opened.');

        try {
            $content = stream_get_contents($handle);
            if ($content === false) $this->fail('The CSV could not be read. Nothing was imported.');
            if (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
                $this->fail('Use UTF-8 text without null bytes. Nothing was imported.');
            }
            $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
            $this->validateQuotes($content);
            rewind($handle);
            if (fread($handle, 3) !== "\xEF\xBB\xBF") rewind($handle);
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header === false) $this->fail('The CSV file is empty.');
            $header = array_map(fn ($value): string => strtolower(trim((string) $value)), $header);
            if (! in_array('word', $header, true) || count(array_unique($header)) !== count($header)
                || array_diff($header, self::HEADERS) !== [] || count($header) > 2) {
                $this->fail('Use the word header, with an optional aliases header. Each header must occur once.');
            }
            $rows = [];
            $errors = [];
            $record = 1;
            $nonempty = 0;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $record++;
                if (count(array_filter($values, fn ($v): bool => trim((string) $v) !== '')) === 0) continue;
                if (++$nonempty > self::MAX_WORDS) $this->fail('Import no more than 2,000 words at a time.');
                if (count($values) !== count($header)) {
                    $errors[] = 'CSV record '.$record.' has the wrong number of columns.';
                    continue;
                }
                $source = array_combine($header, $values);
                $word = self::clean((string) $source['word']);
                if ($word === '' || mb_strlen($word, 'UTF-8') > 100) {
                    $errors[] = 'CSV record '.$record.': word must contain 1–100 characters.';
                    continue;
                }
                $aliases = [];
                $seen = [self::normalize($word) => true];
                $rawAliases = self::clean((string) ($source['aliases'] ?? ''));
                foreach ($rawAliases === '' ? [] : explode('|', $rawAliases) as $alias) {
                    $alias = self::clean($alias);
                    if ($alias === '' || mb_strlen($alias, 'UTF-8') > 100) {
                        $errors[] = 'CSV record '.$record.': each alias must contain 1–100 characters.';
                        continue;
                    }
                    $key = self::normalize($alias);
                    if (! isset($seen[$key])) {
                        $aliases[] = $alias;
                        $seen[$key] = true;
                    }
                }
                if (count($aliases) > 10) $errors[] = 'CSV record '.$record.': use at most 10 different aliases.';
                $rows[] = ['word' => $word, 'aliases' => $aliases];
            }
            if (! feof($handle)) $this->fail('The CSV could not be read completely. Nothing was imported.');
            if ($errors !== []) throw ValidationException::withMessages(['csv' => array_slice($errors, 0, 20)]);
            if ($rows === []) $this->fail('The CSV must contain at least one drawing word.');

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function validateQuotes(string $content): void
    {
        $state = 'start';
        $length = strlen($content);
        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];
            if ($state === 'quoted') {
                if ($char === '"') {
                    if ($i + 1 < $length && $content[$i + 1] === '"') $i++;
                    else $state = 'closed';
                }
                continue;
            }
            if ($char === ',' || $char === "\r" || $char === "\n") { $state = 'start'; continue; }
            if (($state === 'start' || $state === 'closed') && ($char === ' ' || $char === "\t")) continue;
            if ($char === '"' && $state === 'start') { $state = 'quoted'; continue; }
            if ($char === '"' || $state === 'closed') $this->fail('The CSV contains invalid quoting. Nothing was imported.');
            $state = 'unquoted';
        }
        if ($state === 'quoted') $this->fail('The CSV has an unterminated quoted field. Nothing was imported.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['csv' => $message]);
    }
}
