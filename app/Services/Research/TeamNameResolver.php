<?php

namespace App\Services\Research;

use Illuminate\Support\Str;

class TeamNameResolver
{
    public function normalize(string $name): string
    {
        $normalized = Str::of($name)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->trim()
            ->toString();

        $normalized = preg_replace('/\b(fc|sc|cf|ac|cd|de|real|club)\b/', '', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    public function matches(string $a, string $b): bool
    {
        return $this->normalize($a) === $this->normalize($b);
    }
}
