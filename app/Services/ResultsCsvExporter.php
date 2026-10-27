<?php

namespace App\Services;

use App\Models\GameSession;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultsCsvExporter
{
    public function download(GameSession $session): StreamedResponse
    {
        $session = GameSession::query()->findOrFail($session->getKey());

        abort_unless(
            $session->status === GameSession::STATUS_COMPLETED && $session->completed_at !== null,
            409,
            'Only completed sessions can be exported.'
        );
        $catalog = app(RpgProfileCatalog::class);
        $slotAwards = [];
        if ($session->isSlots()) {
            SlotsSessionService::validatePlan($session);
            $rolls = $session->slot_plan['rolls'];
            ksort($rolls, SORT_NUMERIC);
            foreach ($rolls as $position => $roll) {
                foreach ($roll['players'] as $playerId => $outcome) {
                    if ($outcome['prize'] !== null) {
                        $slotAwards[$playerId][] = ['prize' => $outcome['prize'], 'position' => (int) $position];
                    }
                }
            }
        }

        return response()->streamDownload(function () use ($session, $catalog, $slotAwards): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new \RuntimeException('The results download could not be opened.');
            }

            try {
                fwrite($output, "\xEF\xBB\xBF");
                $headers = [
                    'session_id', 'session_slug', 'game_id', 'game_title',
                    'started_at', 'completed_at', 'result_id', 'game_session_player_id',
                    'player_public_id', 'player_name', 'player_email', 'rank', 'is_winner',
                    'total_score', 'correct_answers', 'wrong_answers', 'unanswered_questions',
                    'total_questions', 'total_response_time_ms', 'finalized_at',
                    'gender', 'rpg_profile_id', 'rpg_profile_name', 'rpg_profile_class',
                    'game_type', 'guess_points', 'draw_points',
                    'bingo_cards_remaining', 'bingo_eliminated_on_draw',
                ];
                if ($session->isSlots()) {
                    array_push($headers, 'slot_prize', 'slot_won_position', 'slot_awards');
                }
                fputcsv($output, $headers, ',', '"', '');

                foreach ($session->results()->with('player')->lazy(200) as $result) {
                    $profile = $result->player?->rpg_profile_id
                        ? $catalog->profile($result->player->rpg_profile_id)
                        : null;
                    $row = [
                        $session->id,
                        $session->slug,
                        $session->game_id,
                        $session->game_title,
                        $session->started_at?->toISOString(),
                        $session->completed_at?->toISOString(),
                        $result->id,
                        $result->game_session_player_id,
                        $result->player?->public_id,
                        $result->player_name,
                        $result->player_email,
                        $result->rank,
                        $result->rank === 1 ? 1 : 0,
                        $result->total_score,
                        $result->correct_answers,
                        $result->wrong_answers,
                        $result->unanswered_questions,
                        $result->total_questions,
                        $result->total_response_time_ms,
                        $result->finalized_at?->toISOString(),
                        $result->player?->gender,
                        $result->player?->rpg_profile_id,
                        $profile['name'] ?? '',
                        $profile['class'] ?? '',
                        $session->game_type,
                        $result->guess_points,
                        $result->draw_points,
                        $session->isBingo() ? $result->bingo_cards_remaining : '',
                        $session->isBingo() ? $result->bingo_eliminated_on_draw : '',
                    ];
                    if ($session->isSlots()) {
                        array_push($row, $result->slot_prize, $result->slot_won_position,
                            json_encode($slotAwards[$result->game_session_player_id] ?? [], JSON_THROW_ON_ERROR));
                    }

                    fputcsv($output, array_map($this->safeCell(...), $row), ',', '"', '');
                }
            } finally {
                fclose($output);
            }
        }, 'omiquiz-results-session-'.$session->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeCell(mixed $value): string|int
    {
        if (is_int($value)) {
            return $value;
        }

        $value = (string) ($value ?? '');

        // Spreadsheet formula prefixes remain dangerous after leading whitespace.
        if (preg_match('/^(?:[\s\p{Z}\p{Cc}\x{FEFF}]*[=+\-@]|[\t\r\n])/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
