<?php

namespace App\Services;

use InvalidArgumentException;

/** Makes the private, immutable number/card schedule when the lobby starts. */
class BingoPlanGenerator
{
    public function make(array $playerIds, int $minPlays, int $maxPlays): array
    {
        if (! array_is_list($playerIds) || count($playerIds) < 2 || count($playerIds) > 32
            || count(array_unique($playerIds, SORT_REGULAR)) !== count($playerIds)) {
            throw new InvalidArgumentException('Reverse Bingo needs between 2 and 32 distinct players.');
        }
        foreach ($playerIds as $id) {
            if (! is_int($id) || $id < 1) {
                throw new InvalidArgumentException('Reverse Bingo player identifiers must be positive integers.');
            }
        }
        if ($maxPlays < 6 || $maxPlays > 50 || $minPlays < 3 || $minPlays > $maxPlays - 3) {
            throw new InvalidArgumentException('Use 6–50 plays and an earliest elimination from 3 to three plays before the end.');
        }

        $count = count($playerIds);
        // Reserve at least five uncalled numbers in every B/I/N/G/O column.
        // A full untouched winning board remains possible even at the 50-play limit.
        $callable = [];
        for ($column = 0; $column < 5; $column++) {
            $numbers = $this->shuffled(range(1 + 15 * $column, 15 + 15 * $column));
            array_push($callable, ...array_slice($numbers, 5));
        }
        $draws = array_slice($this->shuffled($callable), 0, $maxPlays);
        $safe = array_values(array_diff(range(1, 75), $draws));
        // Retained as the private legacy identifier; it is never a called number.
        $winningNumber = $safe[random_int(0, count($safe) - 1)];
        $order = $this->shuffled($playerIds);
        $winnerId = array_shift($order);
        $eliminations = array_fill(1, $maxPlays, 0);

        if ($count > 4) {
            $earlyCount = $count - 4;
            $earlyPlays = $maxPlays - $minPlays - 2;
            for ($offset = 0; $offset < $earlyPlays; $offset++) {
                // Spread losses as evenly as possible; with 32 players/20 plays
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

        $seen = [];
        foreach ($players as &$person) {
            foreach ($person['cards'] as &$number) {
                $number = ['number' => $number, 'cells' => $this->board($number, $draws, $seen)];
            }
            unset($number);
        }
        unset($person);

        return ['version' => 2, 'draws' => $draws, 'players' => $players, 'forecast' => $forecast];
    }

    /**
     * A card closes on the first number called anywhere on it. Its required
     * trigger is mixed with later/uncalled numbers, so the forecast follows the
     * visible board itself instead of ignoring matches in decorative cells.
     * Cells are row-major with a null FREE center. No two cards share a set.
     */
    private function board(int $trigger, array $draws, array &$seen): array
    {
        $positions = array_flip($draws);
        $triggerPosition = $positions[$trigger] ?? count($draws);
        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $columns = [];
            for ($column = 0; $column < 5; $column++) {
                $length = $column === 2 ? 4 : 5;
                $pool = array_values(array_filter(range(1 + 15 * $column, 15 + 15 * $column),
                    fn (int $number): bool => $number !== $trigger
                        && (! isset($positions[$number]) || $positions[$number] > $triggerPosition)));
                $required = intdiv($trigger - 1, 15) === $column ? [$trigger] : [];
                $values = array_merge($required, array_slice($this->shuffled($pool), 0, $length - count($required)));
                if (count($values) !== $length) {
                    throw new \LogicException('Not enough safe numbers to create a complete Bingo board.');
                }
                $columns[$column] = $this->shuffled($values);
                if ($column === 2) array_splice($columns[$column], 2, 0, [null]);
            }
            $cells = [];
            for ($row = 0; $row < 5; $row++) {
                for ($column = 0; $column < 5; $column++) $cells[] = $columns[$column][$row];
            }
            $set = array_values(array_filter($cells, fn ($value): bool => $value !== null));
            sort($set);
            $key = implode(',', $set);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                return $cells;
            }
        }
        throw new \LogicException('Unable to create distinct Bingo boards. Start a new session.');
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
