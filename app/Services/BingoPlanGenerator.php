<?php

namespace App\Services;

use InvalidArgumentException;

/** Makes the private, immutable number/card schedule when the lobby starts. */
class BingoPlanGenerator
{
    public function make(array $playerIds, int $minPlays, int $maxPlays): array
    {
        if (! array_is_list($playerIds) || count($playerIds) < 2 || count($playerIds) > 30
            || count(array_unique($playerIds, SORT_REGULAR)) !== count($playerIds)) {
            throw new InvalidArgumentException('Reverse Bingo needs between 2 and 30 distinct players.');
        }
        foreach ($playerIds as $id) {
            if (! is_int($id) || $id < 1) {
                throw new InvalidArgumentException('Reverse Bingo player identifiers must be positive integers.');
            }
        }
        if ($maxPlays < 6 || $maxPlays > 74 || $minPlays < 3 || $minPlays > $maxPlays - 3) {
            throw new InvalidArgumentException('Use 6–74 plays and an earliest elimination from 3 to three plays before the end.');
        }

        $count = count($playerIds);
        $numbers = $this->shuffled(range(1, 75));
        $draws = array_slice($numbers, 0, $maxPlays);
        // This number is absent from every draw and every other player's cards.
        $winningNumber = $numbers[$maxPlays];
        $order = $this->shuffled($playerIds);
        $winnerId = array_shift($order);
        $eliminations = array_fill(1, $maxPlays, 0);

        if ($count > 4) {
            $earlyCount = $count - 4;
            $earlyPlays = $maxPlays - $minPlays - 2;
            for ($offset = 0; $offset < $earlyPlays; $offset++) {
                // Spread losses as evenly as possible; with 30 players/20 plays
                // the early draws eliminate at most two players apiece.
                $eliminations[$minPlays + $offset] = intdiv(($offset + 1) * $earlyCount, $earlyPlays)
                    - intdiv($offset * $earlyCount, $earlyPlays);
            }
        }
        $finalCount = min(3, $count - 1);
        for ($position = $maxPlays - $finalCount + 1; $position <= $maxPlays; $position++) {
            $eliminations[$position] = 1;
        }

        $winnerEarlier = $this->shuffled(range(1, $maxPlays - 1));
        $players = [[
            'player_id' => $winnerId,
            'rank' => 1,
            'eliminated_on_draw' => null,
            'cards' => $this->shuffled([
                $draws[$winnerEarlier[0] - 1], $draws[$winnerEarlier[1] - 1], $winningNumber,
            ]),
        ]];
        $forecast = [];
        $remaining = $count;
        $loserIndex = 0;
        foreach ($eliminations as $position => $eliminated) {
            $forecast[] = [
                'position' => $position,
                'players_before' => $remaining,
                'players_after' => $remaining - $eliminated,
                'eliminated' => $eliminated,
            ];
            for ($index = 0; $index < $eliminated; $index++) {
                $earlier = $this->shuffled(range(1, $position - 1));
                $players[] = [
                    'player_id' => $order[$loserIndex],
                    // A random priority established here separates same-draw ties.
                    'rank' => $count - $loserIndex,
                    'eliminated_on_draw' => $position,
                    'cards' => $this->shuffled([
                        $draws[$earlier[0] - 1], $draws[$earlier[1] - 1], $draws[$position - 1],
                    ]),
                ];
                $loserIndex++;
            }
            $remaining -= $eliminated;
        }

        return ['draws' => $draws, 'players' => $players, 'forecast' => $forecast];
    }

    /** Fisher–Yates uses the system random source for numbers and player order. */
    private function shuffled(array $values): array
    {
        $values = array_values($values);
        for ($index = count($values) - 1; $index > 0; $index--) {
            $other = random_int(0, $index);
            [$values[$index], $values[$other]] = [$values[$other], $values[$index]];
        }

        return $values;
    }
}
