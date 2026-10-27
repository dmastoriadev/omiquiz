<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Builds one private result schedule; the session stores it once at start. */
class SlotsPlanGenerator
{
    public const TOTAL_ROLLS = 5;
    public const DEFAULT_PRIZE_POSITIONS = ['bronze' => 2, 'silver' => 4, 'black' => [1, 3, 4], 'gold' => 5];
    public const PRIZE_POINTS = ['gold' => 4, 'silver' => 3, 'bronze' => 2, 'black' => 1];

    public static function prizePositionsForPlayers(int $players): array
    {
        if ($players < 6 || $players > 200) {
            throw ValidationException::withMessages(['players' => 'Slots needs between 6 and 200 distinct players.']);
        }
        return self::DEFAULT_PRIZE_POSITIONS;
    }

    public static function normalizePrizePositions(?array $positions): array
    {
        if ($positions === null) return self::DEFAULT_PRIZE_POSITIONS;
        if (count($positions) !== count(self::DEFAULT_PRIZE_POSITIONS)
            || array_diff(array_keys(self::DEFAULT_PRIZE_POSITIONS), array_keys($positions)) !== []) {
            throw ValidationException::withMessages([
                'slot_prize_positions' => 'Slots uses a fixed five-roll prize schedule.',
            ]);
        }
        foreach (self::DEFAULT_PRIZE_POSITIONS as $prize => $position) {
            if ($positions[$prize] !== $position) {
                throw ValidationException::withMessages([
                    'slot_prize_positions.'.$prize => 'Slots awards Black on rolls 1, 3 and 4, Bronze on roll 2, Silver on roll 4, and Gold on roll 5.',
                ]);
            }
        }

        return self::DEFAULT_PRIZE_POSITIONS;
    }

    public function generate(array $playerIds, ?array $positions = null): array
    {
        if (! array_is_list($playerIds) || count($playerIds) < 6 || count($playerIds) > 200
            || count(array_unique($playerIds, SORT_REGULAR)) !== count($playerIds)) {
            throw ValidationException::withMessages(['players' => 'Slots needs between 6 and 200 distinct players.']);
        }
        foreach ($playerIds as $id) {
            if (! is_int($id) || $id < 1) {
                throw ValidationException::withMessages(['players' => 'Player identifiers must be positive integers.']);
            }
        }
        self::normalizePrizePositions($positions);
        $positions = self::prizePositionsForPlayers(count($playerIds));

        // One random ordering assigns each of the six awards to a different
        // person. Nobody can receive another prize after their first win.
        $order = $this->shuffled($playerIds);
        $winners = [];
        foreach ($positions['black'] as $index => $position) {
            $winners[$position][$order[$index]] = 'black';
        }
        $medalWinners = array_slice($order, count($positions['black']), 3);
        foreach (['bronze', 'silver', 'gold'] as $index => $prize) {
            $winners[$positions[$prize]][$medalWinners[$index]] = $prize;
        }

        $rolls = [];
        for ($position = 1; $position <= self::TOTAL_ROLLS; $position++) {
            $winning = $winners[$position] ?? [];
            $players = [];
            foreach ($playerIds as $playerId) {
                $prize = $winning[$playerId] ?? null;
                $players[$playerId] = [
                    'reels' => $prize === null ? $this->losingReels() : array_fill(0, 3, $prize),
                    'prize' => $prize,
                ];
            }
            $rolls[$position] = [
                'position' => $position,
                'roll' => $position,
                'players' => $players,
            ];
        }

        return ['version' => 5, 'total_rolls' => self::TOTAL_ROLLS, 'player_ids' => $playerIds, 'prize_positions' => $positions, 'rolls' => $rolls];
    }

    /** Uniformly sample the 60 possible non-winning three-reel combinations. */
    private function losingReels(): array
    {
        $symbols = array_keys(self::PRIZE_POINTS);
        do {
            $reels = [$symbols[random_int(0, 3)], $symbols[random_int(0, 3)], $symbols[random_int(0, 3)]];
        } while ($reels[0] === $reels[1] && $reels[1] === $reels[2]);

        return $reels;
    }

    private function shuffled(array $values): array
    {
        for ($index = count($values) - 1; $index > 0; $index--) {
            $other = random_int(0, $index);
            [$values[$index], $values[$other]] = [$values[$other], $values[$index]];
        }

        return $values;
    }
}
