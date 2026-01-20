<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\SuggestSimilarString;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

use function str_repeat;

class SuggestSimilarStringTest extends TestCase
{
    use SuggestSimilarString;

    public function testFindSimilarStringWithExactMatch(): void
    {
        $available = ['Artist', 'Performance', 'Recording'];
        $result    = $this->findSimilarString('Artist', $available);

        $this->assertEquals('Artist', $result);
    }

    public function testFindSimilarStringWithTypo(): void
    {
        $available = ['Artist', 'Performance', 'Recording'];
        $result    = $this->findSimilarString('Artistt', $available);

        $this->assertEquals('Artist', $result);
    }

    public function testFindSimilarStringWithCaseInsensitiveTypo(): void
    {
        $available = ['Artist', 'Performance', 'Recording'];
        $result    = $this->findSimilarString('artist', $available);

        $this->assertEquals('Artist', $result);
    }

    public function testFindSimilarStringWithMultipleCharTypo(): void
    {
        $available = ['Artist', 'Performance', 'Recording'];
        $result    = $this->findSimilarString('Performence', $available);

        $this->assertEquals('Performance', $result);
    }

    public function testFindSimilarStringWithTransposedLetters(): void
    {
        $available = ['Artist', 'Performance', 'Recording'];
        $result    = $this->findSimilarString('Recorindg', $available);

        $this->assertEquals('Recording', $result);
    }

    public function testFindSimilarStringReturnsNullWhenNoMatch(): void
    {
        $available = ['Artist', 'Performance', 'Recording'];
        $result    = $this->findSimilarString('CompletelyDifferent', $available);

        $this->assertNull($result);
    }

    public function testFindSimilarStringReturnsNullWhenEmptyArray(): void
    {
        $result = $this->findSimilarString('Artist', []);

        $this->assertNull($result);
    }

    public function testFindSimilarStringWithCustomThreshold(): void
    {
        $available = ['Artist', 'Performance', 'Recording'];

        // With threshold of 1, 'Artistt' (distance 1) should match
        $result = $this->findSimilarString('Artistt', $available, 1);
        $this->assertEquals('Artist', $result);

        // With threshold of 1, 'Artisttt' (distance 2) should not match
        $result = $this->findSimilarString('Artisttt', $available, 1);
        $this->assertNull($result);
    }

    public function testFindSimilarStringsReturnsMultipleSuggestions(): void
    {
        $available = ['Artist', 'Artistic', 'Artisan'];
        $results   = $this->findSimilarStrings('Arist', $available);

        // Should return all three sorted by similarity
        $this->assertCount(3, $results);
        $this->assertEquals('Artist', $results[0]); // Closest match
    }

    public function testFindSimilarStringsRespectsMaxResults(): void
    {
        $available = ['Artist', 'Artistic', 'Artisan', 'Art'];
        $results   = $this->findSimilarStrings('Arist', $available, maxResults: 2);

        $this->assertCount(2, $results);
    }

    public function testFindSimilarStringsReturnsEmptyArrayWhenNoMatch(): void
    {
        $available = ['Zebra', 'Elephant', 'Giraffe'];
        $results   = $this->findSimilarStrings('Artist', $available);

        $this->assertEmpty($results);
    }

    public function testFindSimilarStringsReturnsEmptyArrayWhenEmptyInput(): void
    {
        $results = $this->findSimilarStrings('test', []);

        $this->assertEmpty($results);
    }

    public function testFindSimilarStringsSkipsVeryLongStrings(): void
    {
        // Levenshtein has a 255 character limit
        $longString = str_repeat('a', 256);
        $available  = [$longString, 'test', 'tests', 'testing'];

        $results = $this->findSimilarStrings('test', $available, maxResults: 10, threshold: 5);

        // Should skip the too-long string and only return valid matches
        $this->assertNotContains($longString, $results);
        $this->assertContains('test', $results);
        $this->assertCount(3, $results); // 'test', 'tests', 'testing'
    }

    public function testFindSimilarStringsOrdersByDistance(): void
    {
        $available = ['Recording', 'Recordi', 'Record'];
        $results   = $this->findSimilarStrings('Recor', $available);

        // 'Record' is closest (distance 1), then 'Recordi' (distance 2), then 'Recording' (distance 4)
        $this->assertEquals('Record', $results[0]);
    }

    public function testCalculateThresholdForVeryShortStrings(): void
    {
        $this->assertEquals(1, $this->calculateThreshold('ab'));
        $this->assertEquals(1, $this->calculateThreshold('abc'));
    }

    public function testCalculateThresholdForShortStrings(): void
    {
        $this->assertEquals(2, $this->calculateThreshold('abcd'));
        $this->assertEquals(2, $this->calculateThreshold('abcdef'));
    }

    public function testCalculateThresholdForMediumStrings(): void
    {
        $this->assertEquals(3, $this->calculateThreshold('abcdefgh'));
        $this->assertEquals(3, $this->calculateThreshold('abcdefghijkl'));
    }

    public function testCalculateThresholdForLongStrings(): void
    {
        // For strings > 12 chars, use 25% of length, max 5
        $this->assertEquals(3, $this->calculateThreshold('abcdefghijklm')); // 13 * 0.25 = 3.25 -> 3
        $this->assertEquals(5, $this->calculateThreshold('abcdefghijklmnopqrst')); // 20 * 0.25 = 5
        $this->assertEquals(5, $this->calculateThreshold('abcdefghijklmnopqrstuvwxyz')); // 26 * 0.25 = 6.5 -> max 5
    }

    public function testFindSimilarStringSkipsVeryLongStrings(): void
    {
        // Levenshtein has a 255 character limit
        $longString = str_repeat('a', 256);
        $available  = [$longString, 'Artist'];

        $result = $this->findSimilarString('Artist', $available);

        // Should match 'Artist' and skip the too-long string
        $this->assertEquals('Artist', $result);
    }

    public function testFindSimilarStringWithCommonPrefixes(): void
    {
        $available = ['getUserById', 'getUserByEmail', 'getUserByName'];
        $result    = $this->findSimilarString('getUserByld', $available);

        // Should suggest 'getUserById' (typo: l instead of I)
        $this->assertEquals('getUserById', $result);
    }

    public function testFindSimilarStringWithNamespaces(): void
    {
        $available = [
            'App\\Entity\\Artist',
            'App\\Entity\\Performance',
            'App\\Entity\\Recording',
        ];

        $result = $this->findSimilarString('App\\Entity\\Performence', $available);

        $this->assertEquals('App\\Entity\\Performance', $result);
    }

    public function testFindSimilarStringCaseInsensitive(): void
    {
        $available = ['DateTime', 'DateTimeImmutable', 'DateTimeTZ'];
        $result    = $this->findSimilarString('datetime', $available);

        // Should find 'DateTime' despite case difference
        $this->assertEquals('DateTime', $result);
    }

    public function testFindSimilarStringsWithThreshold(): void
    {
        $available = ['Artist', 'Performance', 'Recording'];

        // With tight threshold, fewer suggestions
        $results = $this->findSimilarStrings('Arist', $available, maxResults: 10, threshold: 1);
        $this->assertCount(1, $results);
        $this->assertEquals('Artist', $results[0]);

        // With looser threshold, possibly more suggestions
        $results = $this->findSimilarStrings('Arist', $available, maxResults: 10, threshold: 5);
        $this->assertCount(1, $results); // Still just one close match
    }
}
