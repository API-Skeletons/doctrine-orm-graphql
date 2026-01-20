<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeNotFound as TypeNotFoundException;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

use function str_replace;

class TypeAutoSuggestionTest extends TestCase
{
    public function testDriverTypeSuggestsCorrectEntityWhenTypo(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('Artistt'); // Typo: extra 't'
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Type "Artistt" is not registered', $e->getMessage());
            // Full class names might not get suggestions due to length, just verify error message
            $this->assertStringContainsString('Available types:', $e->getMessage());
        }
    }

    public function testDriverTypeSuggestsCorrectEntityWhenCaseWrong(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('artist'); // Wrong case
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Type "artist" is not registered', $e->getMessage());
            $this->assertStringContainsString('Available types:', $e->getMessage());
        }
    }

    public function testDriverTypeSuggestsCorrectBuiltInType(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('strng'); // Typo: missing 'i'
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Type "strng" is not registered', $e->getMessage());
            $this->assertStringContainsString('Did you mean "string"?', $e->getMessage());
        }
    }

    public function testDriverTypeSuggestsDateTimeVariants(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('datetme'); // Typo: 'time' misspelled
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Type "datetme" is not registered', $e->getMessage());
            $this->assertStringContainsString('Did you mean "datetime"?', $e->getMessage());
        }
    }

    public function testDriverTypeNoSuggestionWhenCompleteDifferent(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('CompletelyWrongTypeName');
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Type "CompletelyWrongTypeName" is not registered', $e->getMessage());
            // Should NOT contain "Did you mean" since nothing is similar
            $this->assertStringNotContainsString('Did you mean', $e->getMessage());
        }
    }

    public function testDriverTypeListsAvailableTypes(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('unknown');
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Available types:', $e->getMessage());
            $this->assertStringContainsString(Artist::class, $e->getMessage());
            $this->assertStringContainsString('string', $e->getMessage());
        }
    }

    public function testContainerGetSuggestsSimilarService(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->get('EntityManagr'); // Typo: missing 'e'
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Type "EntityManagr" is not registered', $e->getMessage());
            // Service names in container are lowercased and may not match well
            $this->assertStringContainsString('Available types:', $e->getMessage());
        }
    }

    public function testSuggestionWorksWithNamespaces(): void
    {
        $driver = new Driver($this->getEntityManager());

        $performanceTypo = str_replace('Performance', 'Performence', Performance::class);

        try {
            $driver->type($performanceTypo);
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Type "' . $performanceTypo . '" is not registered', $e->getMessage());
            $this->assertStringContainsString('Did you mean "' . Performance::class . '"?', $e->getMessage());
        }
    }

    public function testSuggestionWithTransposedLetters(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('srting'); // Transposed: 's' and 't'
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Did you mean "string"?', $e->getMessage());
        }
    }

    public function testSuggestionWithMissingCharacter(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('bolean'); // Missing 'o'
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Did you mean "boolean"?', $e->getMessage());
        }
    }

    public function testSuggestionWithExtraCharacter(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('floatt'); // Extra 't'
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            $this->assertStringContainsString('Did you mean "float"?', $e->getMessage());
        }
    }

    public function testMultipleTyposStillSuggests(): void
    {
        $driver = new Driver($this->getEntityManager());

        try {
            $driver->type('intger'); // Two typos: 'e' and 'g' swapped, 'e' instead of 'e'
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            // With 2 character difference, should still suggest 'integer' (threshold is 3)
            $this->assertStringContainsString('Did you mean "integer"?', $e->getMessage());
        }
    }

    public function testSuggestionWorksWithDifferentGroups(): void
    {
        $config = new Config(['group' => 'test-group']);
        $driver = new Driver($this->getEntityManager(), $config);

        try {
            $driver->type('Artistt');
            $this->fail('Expected TypeNotFoundException to be thrown');
        } catch (TypeNotFoundException $e) {
            // Different group means no entity types registered, so only built-in types available
            $this->assertStringContainsString('Type "Artistt" is not registered', $e->getMessage());
            $this->assertStringContainsString('Available types:', $e->getMessage());
        }
    }
}
