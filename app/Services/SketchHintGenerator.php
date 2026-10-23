<?php

namespace App\Services;

/** A hint is selected once, then stored on its turn before anyone sees it. */
class SketchHintGenerator
{
    public function make(string $word, int $maximum): ?string
    {
        if ($maximum <= 0 || ! preg_match_all('/\X/u', $word, $matches)) return null;
        $characters = $matches[0];
        $positions = [];
        foreach ($characters as $index => $character) {
            if (preg_match('/[\p{L}\p{N}]/u', $character)) $positions[] = $index;
        }
        // Punctuation-only or emoji-only prompts have no letters to hint.
        if ($positions === []) return null;
        $count = min($maximum, count($positions) - 1);
        $revealed = [];
        for ($i = 0; $i < $count; $i++) {
            $selection = random_int(0, count($positions) - 1);
            $revealed[$positions[$selection]] = true;
            array_splice($positions, $selection, 1);
        }
        foreach ($characters as $index => &$character) {
            // Keep word separators readable. Other symbols never reveal a prompt.
            if (! isset($revealed[$index]) && ! preg_match('/^[\p{Z}\p{P}\s]+$/u', $character)) $character = '_';
        }
        unset($character);
        return implode('', $characters);
    }
}
