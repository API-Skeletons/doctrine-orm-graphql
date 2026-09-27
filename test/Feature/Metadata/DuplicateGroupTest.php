<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function ini_get;
use function ini_set;

/**
 * Two attributes for the same group on one entity, field, association or
 * computed field is a metadata error.  The check does not depend on
 * assertions, which are disabled in production.
 */
class DuplicateGroupTest extends TestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function duplicateProvider(): array
    {
        return [
            'entity' => ['DuplicateGroup', '0', 'Duplicate attribute found for entity'],
            'field' => ['DuplicateGroupField', '0', 'Duplicate attribute found for field name'],
            'association' => ['DuplicateGroupAssociation', '0', 'Duplicate attribute found for association'],
            'computed field' => [
                'DuplicateGroupComputedField',
                '0',
                'Duplicate ComputedField attribute found for method getFullName',
            ],
            'entity, assertions on' => ['DuplicateGroup', '1', 'Duplicate attribute found for entity'],
            'field, assertions on' => ['DuplicateGroupField', '1', 'Duplicate attribute found for field name'],
        ];
    }

    #[DataProvider('duplicateProvider')]
    public function testDuplicateAttributeThrowsMetadataException(
        string $group,
        string $assertions,
        string $message,
    ): void {
        $previous = ini_get('zend.assertions');
        // zend.assertions=-1 cannot be changed at runtime; assertions are already off
        if ($previous !== '-1') {
            ini_set('zend.assertions', $assertions);
        }

        try {
            $driver = new Driver($this->getEntityManager(), new Config(['group' => $group]));

            $this->expectException(MetadataException::class);
            $this->expectExceptionMessage($message);

            $driver->get(Metadata::class);
        } finally {
            if ($previous !== '-1') {
                ini_set('zend.assertions', (string) $previous);
            }
        }
    }
}
