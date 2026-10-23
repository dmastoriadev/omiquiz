<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\SketchWord;
use App\Services\SketchWordCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SketchWordController extends Controller
{
    public function store(Request $request, Game $game, SketchWordCsvImporter $importer): RedirectResponse
    {
        abort_unless($game->game_type === 'sketch', 404);
        $request->validate(['csv' => ['required', 'file', 'max:2048', 'extensions:csv']]);
        $count = $importer->import($game, $request->file('csv'));

        return redirect()->route('admin.games.show', $game)
            ->with('success', $count.' new drawing words imported. Duplicate words were skipped.');
    }

    public function template(Game $game): StreamedResponse
    {
        abort_unless($game->game_type === 'sketch', 404);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) throw new \RuntimeException('The word template could not be opened.');
            try {
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, SketchWordCsvImporter::HEADERS, ',', '"', '');
                fputcsv($output, ['Bicycle', 'Bike'], ',', '"', '');
                fputcsv($output, ['Airplane', 'Plane|Aeroplane'], ',', '"', '');
                fputcsv($output, ['Campfire', 'Camp fire'], ',', '"', '');
            } finally {
                fclose($output);
            }
        }, 'omiquiz-sketch-words.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Game $game, SketchWord $word): RedirectResponse
    {
        abort_unless($game->game_type === 'sketch' && (int) $word->game_id === (int) $game->getKey(), 404);
        DB::transaction(function () use ($game, $word): void {
            $locked = Game::query()->whereKey($game->getKey())->lockForUpdate()->firstOrFail();
            $locked->words()->whereKey($word->getKey())->firstOrFail()->delete();
        });

        return redirect()->route('admin.games.show', $game)
            ->with('success', 'Word removed from future sessions. Existing session word copies are retained.');
    }
}
