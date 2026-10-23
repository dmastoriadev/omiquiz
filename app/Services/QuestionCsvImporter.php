<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuestionCsvImporter
{
    public const HEADERS = [
        'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'points',
    ];

    public const MAX_QUESTIONS = 500;
    public const MAX_BYTES = 2 * 1024 * 1024;

    public function import(Game $game, UploadedFile $file): int
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['csv' => 'Upload a valid CSV file no larger than 2 MB.']);
        }

        // Parse and validate the whole upload before opening a write transaction.
        $rows = $this->parse($file);

        return DB::transaction(function () use ($game, $rows): int {
            $lockedGame = Game::query()->whereKey($game->getKey())->lockForUpdate()->firstOrFail();
            $existingCount = $lockedGame->questions()->count();

            if ($existingCount + count($rows) > self::MAX_QUESTIONS) {
                throw ValidationException::withMessages([
                    'csv' => 'A game can contain at most 500 questions. It currently contains '.$existingCount.'.',
                ]);
            }

            $position = (int) $lockedGame->questions()->withTrashed()->reorder()->max('position');

            foreach ($rows as $row) {
                $lockedGame->questions()->create($row + [
                    'position' => ++$position,
                    'is_active' => true,
                ]);
            }

            return count($rows);
        }, 3);
    }

    private function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['csv' => 'The CSV file could not be opened.']);
        }

        try {
            // Excel commonly writes a UTF-8 BOM before the first header.
            if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $contentStart = ftell($handle);
            $content = stream_get_contents($handle);

            if ($content === false) {
                throw ValidationException::withMessages(['csv' => 'The CSV could not be read completely. Nothing was imported.']);
            }

            $this->validateQuotes($content);
            fseek($handle, $contentStart);

            $header = fgetcsv($handle, 0, ',', '"', '');

            if ($header === false) {
                throw ValidationException::withMessages(['csv' => 'The CSV file is empty.']);
            }

            $header = array_map(fn ($cell): string => strtolower(trim((string) $cell)), $header);

            if (count($header) !== count(self::HEADERS)
                || count(array_unique($header)) !== count($header)
                || array_diff(self::HEADERS, $header) !== []
                || array_diff($header, self::HEADERS) !== []) {
                throw ValidationException::withMessages([
                    'csv' => 'Use these CSV headers exactly once: '.implode(', ', self::HEADERS).'.',
                ]);
            }

            $rows = [];
            $errors = [];
            $record = 1;
            $nonemptyRecords = 0;

            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                ++$record;

                if ($this->isBlank($values)) {
                    continue;
                }

                if (++$nonemptyRecords > self::MAX_QUESTIONS) {
                    throw ValidationException::withMessages(['csv' => 'Import no more than 500 questions at a time.']);
                }

                if (count($values) !== count($header)) {
                    $errors[] = 'CSV record '.$record.' must contain exactly seven columns.';
                    continue;
                }

                if (! $this->isUtf8Text($values)) {
                    $errors[] = 'CSV record '.$record.' must contain UTF-8 text without null bytes.';
                    continue;
                }

                $source = array_combine($header, array_map(fn ($cell): string => trim((string) $cell), $values));
                $options = [];

                foreach (['A', 'B', 'C', 'D'] as $key) {
                    $value = $source['option_'.strtolower($key)];

                    if ($value !== '') {
                        $options[$key] = $value;
                    }
                }

                $data = [
                    'question_text' => $source['question'],
                    'options' => $options,
                    'correct_option' => strtoupper($source['correct_option']),
                    'points' => $source['points'] === '' ? 1000 : $source['points'],
                ];

                $validator = Validator::make($data, [
                    'question_text' => ['required', 'string', 'max:2000'],
                    'options' => ['required', 'array', 'min:2', 'max:4'],
                    'options.A' => ['required', 'string', 'max:500'],
                    'options.B' => ['required', 'string', 'max:500'],
                    'options.C' => ['sometimes', 'string', 'max:500'],
                    'options.D' => ['sometimes', 'string', 'max:500'],
                    'correct_option' => ['required', Rule::in(array_keys($options))],
                    'points' => ['required', 'integer', 'min:1', 'max:10000'],
                ]);

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $errors[] = 'CSV record '.$record.': '.$message;
                    }

                    continue;
                }

                $data['points'] = (int) $data['points'];
                $rows[] = $data;
            }

            if (! feof($handle)) {
                throw ValidationException::withMessages(['csv' => 'The CSV could not be read completely. Nothing was imported.']);
            }

            if ($errors !== []) {
                $shown = array_slice($errors, 0, 20);

                if (count($errors) > 20) {
                    $shown[] = 'Additional errors were found. Correct the CSV and upload it again.';
                }

                throw ValidationException::withMessages(['csv' => $shown]);
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['csv' => 'The CSV must contain at least one question.']);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function validateQuotes(string $content): void
    {
        // fgetcsv accepts an unterminated last quoted field. Check only quote
        // structure here, leaving CSV decoding and multiline records to fgetcsv.
        $state = 'start';
        $length = strlen($content);

        for ($index = 0; $index < $length; ++$index) {
            $character = $content[$index];

            if ($state === 'quoted') {
                if ($character === '"') {
                    if ($index + 1 < $length && $content[$index + 1] === '"') {
                        ++$index;
                    } else {
                        $state = 'closed';
                    }
                }

                continue;
            }

            if ($character === ',' || $character === "\r" || $character === "\n") {
                $state = 'start';
                continue;
            }

            // Tolerate spaces around a quoted value, as fgetcsv does.
            if (($state === 'start' || $state === 'closed') && ($character === ' ' || $character === "\t")) {
                continue;
            }

            if ($character === '"' && $state === 'start') {
                $state = 'quoted';
                continue;
            }

            if ($character === '"' || $state === 'closed') {
                throw ValidationException::withMessages([
                    'csv' => 'The CSV contains invalid quoting. Put quoted text inside double quotes and double any quotes inside it. Nothing was imported.',
                ]);
            }

            $state = 'unquoted';
        }

        if ($state === 'quoted') {
            throw ValidationException::withMessages(['csv' => 'The CSV has an unterminated quoted field. Nothing was imported.']);
        }
    }

    private function isUtf8Text(array $values): bool
    {
        foreach ($values as $value) {
            $text = (string) $value;

            if (! mb_check_encoding($text, 'UTF-8') || str_contains($text, "\0")) {
                return false;
            }
        }

        return true;
    }
}
