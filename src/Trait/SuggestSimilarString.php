<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Trait;

use function array_filter;
use function array_map;
use function levenshtein;
use function min;
use function strlen;
use function strtolower;
use function usort;

use const ARRAY_FILTER_USE_BOTH;
use const PHP_INT_MAX;

/**
 * Provides functionality to suggest similar strings based on Levenshtein distance
 *
 * Useful for "Did you mean?" suggestions when a string is not found
 */
trait SuggestSimilarString
{
    /**
     * Find the most similar string from available options
     *
     * Uses Levenshtein distance to find the closest match
     *
     * @param string[] $available Available strings to compare against
     * @param int      $threshold Maximum distance to consider (default 3)
     *
     * @return string|null The most similar string, or null if none are similar enough
     */
    protected function findSimilarString(string $target, array $available, int $threshold = 3): string|null
    {
        if ($available === []) {
            return null;
        }

        $targetLower = strtolower($target);
        $minDistance = PHP_INT_MAX;
        $bestMatch   = null;

        foreach ($available as $option) {
            $optionLower = strtolower($option);

            // Levenshtein has a max length of 255 characters
            if (strlen($targetLower) > 255 || strlen($optionLower) > 255) {
                continue;
            }

            $distance = levenshtein($targetLower, $optionLower);

            if ($distance >= $minDistance) {
                continue;
            }

            $minDistance = $distance;
            $bestMatch   = $option;
        }

        // Only return a match if it's within the threshold
        if ($minDistance <= $threshold) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Find multiple similar strings from available options
     *
     * @param string[] $available  Available strings to compare against
     * @param int      $maxResults Maximum number of suggestions to return (default 3)
     * @param int      $threshold  Maximum distance to consider (default 3)
     *
     * @return string[] Array of similar strings, sorted by similarity
     */
    protected function findSimilarStrings(
        string $target,
        array $available,
        int $maxResults = 3,
        int $threshold = 3,
    ): array {
        if ($available === []) {
            return [];
        }

        $targetLower = strtolower($target);
        $suggestions = [];

        foreach ($available as $option) {
            $optionLower = strtolower($option);

            // Levenshtein has a max length of 255 characters
            if (strlen($targetLower) > 255 || strlen($optionLower) > 255) {
                continue;
            }

            $distance = levenshtein($targetLower, $optionLower);

            if ($distance > $threshold) {
                continue;
            }

            $suggestions[] = [
                'value' => $option,
                'distance' => $distance,
            ];
        }

        // Sort by distance (closest first)
        /** @psalm-suppress MixedArrayAccess */
        usort($suggestions, static fn ($a, $b) => $a['distance'] <=> $b['distance']);

        // Return only the values, limited to maxResults
        return array_filter(
            array_map(static fn ($suggestion) => $suggestion['value'], $suggestions),
            static fn ($value, $key) => $key < $maxResults,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Calculate adaptive threshold based on string length
     *
     * Longer strings tolerate more differences
     */
    protected function calculateThreshold(string $target): int
    {
        $length = strlen($target);

        if ($length <= 3) {
            return 1; // Very short strings: allow only 1 character difference
        }

        if ($length <= 6) {
            return 2; // Short strings: allow 2 character difference
        }

        if ($length <= 12) {
            return 3; // Medium strings: allow 3 character difference
        }

        return (int) min(5, (float) $length * 0.25); // Longer strings: allow up to 25% difference, max 5
    }
}
