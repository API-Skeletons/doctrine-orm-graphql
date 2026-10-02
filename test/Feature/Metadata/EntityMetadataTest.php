<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\GraphQL as GraphQLException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\AssociationMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\ComputedFieldMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\EntityMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\FieldMetadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_unique;
use function file_get_contents;
use function glob;
use function preg_match_all;

/**
 * The typed metadata objects are built from, and export exactly, the array
 * form of the metadata which is cached
 */
class EntityMetadataTest extends TestCase
{
    /**
     * Every group used by the test entities
     *
     * @return string[]
     */
    private function getGroups(): array
    {
        $groups = ['default'];

        foreach (glob(__DIR__ . '/../../Entity/*.php') as $file) {
            preg_match_all("/group: '([A-Za-z0-9_]+)'/", (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $group) {
                $groups[] = $group;
            }
        }

        return array_unique($groups);
    }

    /**
     * For every group whose metadata builds, each entity's array survives
     * EntityMetadata::fromArray()->toArray() unchanged, key order included,
     * and a driver built from the exported metadata exports it identically
     */
    public function testEveryGroupRoundTrips(): void
    {
        $roundTripped = 0;

        foreach ($this->getGroups() as $group) {
            $config = new Config(['group' => $group]);

            try {
                $exported = (new Driver($this->getEntityManager(), $config))->get(Metadata::class)->toArray();
            } catch (GraphQLException) {
                // Groups which exist to test invalid metadata or configuration
                continue;
            }

            foreach ($exported as $entityClass => $entityArray) {
                if ($entityClass === Metadata::VERSION_KEY || $entityClass === Metadata::CONFIG_KEY) {
                    continue;
                }

                $this->assertSame(
                    $entityArray,
                    EntityMetadata::fromArray($entityArray)->toArray(),
                    'Group ' . $group . ', entity ' . $entityClass,
                );
            }

            $this->assertSame(
                $exported,
                (new Driver($this->getEntityManager(), $config, $exported))->get(Metadata::class)->toArray(),
                'Group ' . $group,
            );

            $roundTripped++;
        }

        $this->assertGreaterThan(30, $roundTripped);
    }

    public function testFieldsAndAssociationsAreSeparated(): void
    {
        $exported = (new Driver($this->getEntityManager()))->get(Metadata::class)->toArray();

        $artist = EntityMetadata::fromArray($exported[Artist::class]);

        $this->assertInstanceOf(FieldMetadata::class, $artist->fields['name']);
        $this->assertSame('name', $artist->fields['name']->name);
        $this->assertSame('string', $artist->fields['name']->type);
        $this->assertInstanceOf(AssociationMetadata::class, $artist->associations['performances']);
        $this->assertArrayNotHasKey('performances', $artist->fields);
    }

    public function testComputedFields(): void
    {
        $config   = new Config(['group' => 'computedFieldTest']);
        $exported = (new Driver($this->getEntityManager(), $config))->get(Metadata::class)->toArray();

        $artist = EntityMetadata::fromArray($exported[Artist::class]);

        $this->assertInstanceOf(ComputedFieldMetadata::class, $artist->computedFields['fullName']);
        $this->assertSame('getFullName', $artist->computedFields['fullName']->method);
    }

    /**
     * Keys added by a metadata.build listener are ignored by the objects and
     * remain in the array
     */
    public function testUnknownKeysAreIgnored(): void
    {
        $exported = (new Driver($this->getEntityManager()))->get(Metadata::class)->toArray();

        $exported[User::class]['myListenerKey']                   = 'mine';
        $exported[User::class]['fields']['name']['myListenerKey'] = 'mine';

        $user = EntityMetadata::fromArray($exported[User::class]);

        $this->assertSame(User::class, $user->entityClass);
    }

    /** @return array<string, array{callable(array<string, mixed>): array<string, mixed>, string}> */
    public static function invalidProvider(): array
    {
        return [
            'missing entity key' => [
                static function (array $user): array {
                    unset($user['typeName']);

                    return $user;
                },
                'Metadata for entity ' . User::class . ' is missing the key typeName.',
            ],
            'wrong entity type' => [
                static function (array $user): array {
                    $user['extractByValue'] = 'yes';

                    return $user;
                },
                'Metadata for entity ' . User::class . ' key extractByValue must be a bool, string given.',
            ],
            'wrong field type' => [
                static function (array $user): array {
                    $user['fields']['name']['type'] = 5;

                    return $user;
                },
                'Metadata for entity ' . User::class . ' field name key type must be a string, int given.',
            ],
            'wrong association type' => [
                static function (array $user): array {
                    $user['fields']['recordings']['limit'] = '10';

                    return $user;
                },
                'Metadata for entity ' . User::class . ' field recordings key limit must be an int or null, string given.',
            ],
            'field not an array' => [
                static function (array $user): array {
                    $user['fields']['name'] = 'name';

                    return $user;
                },
                'Metadata for entity ' . User::class . ' field name must be an array, string given.',
            ],
            'excludeFilters not a list of strings' => [
                static function (array $user): array {
                    $user['excludeFilters'] = [1];

                    return $user;
                },
                'Metadata for entity ' . User::class . ' key excludeFilters must be a list of strings, array given.',
            ],
            'wrong nullable string type' => [
                static function (array $user): array {
                    $user['description'] = 5;

                    return $user;
                },
                'Metadata for entity ' . User::class . ' key description must be a string or null, int given.',
            ],
            'wrong int type' => [
                static function (array $user): array {
                    $user['limit'] = null;

                    return $user;
                },
                'Metadata for entity ' . User::class . ' key limit must be an int, null given.',
            ],
            'negative limit' => [
                static function (array $user): array {
                    $user['limit'] = -1;

                    return $user;
                },
                'Metadata for entity ' . User::class . ' key limit must be at least 0, -1 given.',
            ],
            'negative association limit' => [
                static function (array $user): array {
                    $user['fields']['recordings']['limit'] = -5;

                    return $user;
                },
                'Metadata for entity ' . User::class . ' field recordings key limit must be at least 0, -5 given.',
            ],
            'excludeFilters not an array' => [
                static function (array $user): array {
                    $user['excludeFilters'] = 'eq';

                    return $user;
                },
                'Metadata for entity ' . User::class . ' key excludeFilters must be a list of strings, string given.',
            ],
            'fields not an array' => [
                static function (array $user): array {
                    $user['fields'] = 'name';

                    return $user;
                },
                'Metadata for entity ' . User::class . ' key fields must be an array, string given.',
            ],
            'entity class does not exist' => [
                static function (array $user): array {
                    $user['entityClass'] = 'App\\Removed\\Entity';

                    return $user;
                },
                'Metadata names entity App\\Removed\\Entity but the class does not exist.',
            ],
            'missing entityClass' => [
                static function (array $user): array {
                    unset($user['entityClass']);

                    return $user;
                },
                'Metadata for an entity is missing the key entityClass.',
            ],
        ];
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $corrupt */
    #[DataProvider('invalidProvider')]
    public function testInvalidMetadataIsReported(callable $corrupt, string $message): void
    {
        $exported = (new Driver($this->getEntityManager()))->get(Metadata::class)->toArray();

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage($message);

        EntityMetadata::fromArray($corrupt($exported[User::class]));
    }

    /**
     * A negative limit would return no rows by default and let first bypass
     * the configured limit
     */
    public function testNegativeLimitFromListenerIsRejected(): void
    {
        $driver = new Driver($this->getEntityManager());
        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event): void {
                $metadata                = $event->getMetadata();
                $artist                  = $metadata[Artist::class];
                $artist['limit']         = -1;
                $metadata[Artist::class] = $artist;
            },
        );

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('Metadata for entity ' . Artist::class . ' key limit must be at least 0, -1 given.');

        $driver->type(Artist::class);
    }

    public function testInvalidComputedFieldIsReported(): void
    {
        $config   = new Config(['group' => 'computedFieldTest']);
        $exported = (new Driver($this->getEntityManager(), $config))->get(Metadata::class)->toArray();
        $artist   = $exported[Artist::class];

        $artist['computedFields']['fullName']['method'] = null;

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage(
            'Metadata for entity ' . Artist::class . ' computed field fullName key method must be a string, null given.',
        );

        EntityMetadata::fromArray($artist);
    }

    /**
     * Every name of an entity's type must be a valid GraphQL name.  The
     * names are made invalid in cached metadata, as an attribute could.
     *
     * @return array<string, array{callable(array<string, mixed>): array<string, mixed>, string}>
     */
    public static function invalidNameProvider(): array
    {
        $performance = 'Metadata for entity ' . Performance::class . ': ';
        $rule        = '  A name must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ and may not begin with "__".';

        return [
            'alias' => [
                static function (array $metadata): array {
                    $metadata['fields']['venue']['alias'] = 'venue-name';

                    return $metadata;
                },
                $performance . 'the alias of venue, "venue-name", is not a valid GraphQL name.' . $rule,
            ],
            'reserved alias' => [
                static function (array $metadata): array {
                    $metadata['fields']['venue']['alias'] = '__venue';

                    return $metadata;
                },
                $performance . 'the alias of venue, "__venue", is not a valid GraphQL name.' . $rule,
            ],
            'association alias' => [
                static function (array $metadata): array {
                    $metadata['fields']['artist']['alias'] = '1artist';

                    return $metadata;
                },
                $performance . 'the alias of artist, "1artist", is not a valid GraphQL name.' . $rule,
            ],
            'type name' => [
                static function (array $metadata): array {
                    $metadata['typeName'] = 'performance type';

                    return $metadata;
                },
                $performance . 'the type name, "performance type", is not a valid GraphQL name.' . $rule,
            ],
            'computed field' => [
                static function (array $metadata): array {
                    $metadata['computedFields'] = [
                        'full-city' => ['method' => 'getCity', 'type' => 'string', 'name' => 'full-city', 'description' => null],
                    ];

                    return $metadata;
                },
                $performance . 'the name of computed field full-city, "full-city", is not a valid GraphQL name.' . $rule,
            ],
        ];
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $invalidate */
    #[DataProvider('invalidNameProvider')]
    public function testInvalidNameIsRejected(callable $invalidate, string $message): void
    {
        $metadata                     = (new Driver($this->getEntityManager()))->get(Metadata::class)->toArray();
        $metadata[Performance::class] = $invalidate($metadata[Performance::class]);

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage($message);

        EntityMetadata::fromArray($metadata[Performance::class]);
    }

    /**
     * A group suffix is part of every type name
     */
    public function testInvalidGroupSuffixIsRejected(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['groupSuffix' => 'my-suffix']));

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('the type name, "performance_my-suffix", is not a valid GraphQL name.');

        $driver->type(Performance::class);
    }
}
