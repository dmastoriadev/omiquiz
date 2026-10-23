<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Question;
use App\Services\QuestionCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionImportController extends Controller
{
    public function store(Request $request, Game $game, QuestionCsvImporter $importer): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'max:2048', 'extensions:csv'],
        ]);

        $count = $importer->import($game, $request->file('csv'));

        return redirect()->route('admin.games.show', $game)
            ->with('success', $count.' questions imported.');
    }

    public function template(Game $game): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new \RuntimeException('The question template download could not be opened.');
            }

            try {
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, QuestionCsvImporter::HEADERS, ',', '"', '');
                fputcsv($output, [
                    'What is the capital of the Philippines?',
                    'Manila', 'Cebu', 'Davao', 'Baguio', 'A', 1000,
                ], ',', '"', '');
                fputcsv($output, [
                    'The Earth orbits the Sun.',
                    'True', 'False', '', '', 'A', 1000,
                ], ',', '"', '');
            } finally {
                fclose($output);
            }
        }, 'omiquiz-question-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Game $game, Question $question): RedirectResponse
    {
        abort_unless((int) $question->game_id === (int) $game->getKey(), 404);

        DB::transaction(function () use ($game, $question): void {
            $lockedGame = Game::query()->whereKey($game->getKey())->lockForUpdate()->firstOrFail();
            $lockedGame->questions()->whereKey($question->getKey())->firstOrFail()->delete();
        });

        return redirect()->route('admin.games.show', $game)
            ->with('success', 'Question removed. Existing session copies have been retained.');
    }
}
