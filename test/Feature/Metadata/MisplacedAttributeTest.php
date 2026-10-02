<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestMisplacedAttributes;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;

/**
 * An attribute in a place it does not apply to is an error rather than
 * silently ignored
 */
class MisplacedAttributeTest extends TestCase
{
    private const string ENTITY = 'of entity ' . TestMisplacedAttributes::class;

    /** @return array<string, array{string, string}> */
    public static function misplacedProvider(): array
    {
        return [
            'Field on an association' => [
                'FieldOnAssociation',
                'Property parent ' . self::ENTITY
                    . ' is an association.  Expose it with an Association attribute, not a Field attribute.',
            ],
            'Field on an unmapped property' => [
                'FieldOnUnmapped',
                'Property unmapped ' . self::ENTITY
                    . ' has a Field attribute but is not a mapped field.  Expose a value which is not a column with a ComputedField.',
            ],
            'Field on an embeddable' => [
                'FieldOnEmbedded',
                'Property address ' . self::ENTITY
                    . ' is an embeddable, whose fields are not exposed.  Expose an embedded value with a ComputedField.',
            ],
            'Field on an unmapped property of a parent class' => [
                'FieldOnParent',
                'Property parentUnmapped ' . self::ENTITY
                    . ' has a Field attribute but is not a mapped field.  Expose a value which is not a column with a ComputedField.',
            ],
            'Association on a field' => [
                'AssociationOnField',
                'Property name ' . self::ENTITY
                    . ' is a field.  Expose it with a Field attribute, not an Association attribute.',
            ],
            'Association on an unmapped property' => [
                'AssociationOnUnmapped',
                'Property unmapped ' . self::ENTITY . ' has an Association attribute but is not a mapped association.',
            ],
            'Association on an embeddable' => [
                'AssociationOnEmbedded',
                'Property address ' . self::ENTITY
                    . ' is an embeddable, whose fields are not exposed.  Expose an embedded value with a ComputedField.',
            ],
            'ComputedField on a private method' => [
                'PrivateComputedField',
                'Method getSecret ' . self::ENTITY . ' has a ComputedField attribute but is not a public, non-static method.',
            ],
            'ComputedField on a static method' => [
                'StaticComputedField',
                'Method getShared ' . self::ENTITY . ' has a ComputedField attribute but is not a public, non-static method.',
            ],
        ];
    }

    #[DataProvider('misplacedProvider')]
    public function testMisplacedAttributeIsAnError(string $group, string $message): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => $group]));

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage($message);

        $driver->get(Metadata::class);
    }

    /**
     * Only the attributes of the configured group are checked
     */
    public function testAttributesOfOtherGroupsAreNotChecked(): void
    {
        $driver   = new Driver($this->getEntityManager(), new Config(['group' => 'Placed']));
        $metadata = $driver->get(Metadata::class)[TestMisplacedAttributes::class];

        $this->assertSame(['id', 'name', 'parent'], array_keys($metadata['fields']));
        $this->assertSame(['label'], array_keys($metadata['computedFields']));
    }
}
