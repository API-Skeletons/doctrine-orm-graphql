<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\FieldResolver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Laminas\Hydrator\HydratorInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

use function array_column;
use function count;
use function gc_collect_cycles;

/**
 * The FieldResolver extracts each entity once, and never serves one entity
 * the values extracted for another
 */
class HydratorCacheTest extends TestCase
{
    /** Wrap the Performance hydrator to count its extracts */
    private function countExtracts(Driver $driver): HydratorInterface
    {
        $hydratorContainer = $driver->get(HydratorContainer::class);
        $hydrator          = $hydratorContainer->get(Performance::class);

        $counter = new class ($hydrator) implements HydratorInterface {
            public int $extracts = 0;

            public function __construct(private HydratorInterface $hydrator)
            {
            }

            /** @return mixed[] */
            #[Override]
            public function extract(object $object): array
            {
                $this->extracts++;

                return $this->hydrator->extract($object);
            }

            /** @param mixed[] $data */
            #[Override]
            public function hydrate(array $data, object $object): object
            {
                return $this->hydrator->hydrate($data, $object);
            }
        };

        $hydratorContainer->set(Performance::class, $counter);

        return $counter;
    }

    private function getSchema(Driver $driver): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);
    }

    /** @return array<string, array{bool}> */
    public static function cacheProvider(): array
    {
        return [
            'hydrator cache' => [true],
            'no hydrator cache' => [false],
        ];
    }

    /**
     * One row has a null venue, city and state.  A null field must not cause
     * the entity to be extracted again.
     */
    #[DataProvider('cacheProvider')]
    public function testEachEntityIsExtractedOnce(bool $useHydratorCache): void
    {
        $driver  = new Driver($this->getEntityManager(), new Config(['useHydratorCache' => $useHydratorCache]));
        $counter = $this->countExtracts($driver);

        $result = GraphQL::executeQuery(
            $this->getSchema($driver),
            '{ performance { edges { node { id venue city state } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        $nodes = array_column($result['data']['performance']['edges'], 'node');
        $this->assertContains(null, array_column($nodes, 'venue'));
        $this->assertSame(count($nodes), $counter->extracts);
    }

    /**
     * In a long running process one resolver serves many requests.  After the
     * entity manager is cleared, a new entity object must not be served the
     * values extracted for a freed one.
     */
    #[DataProvider('cacheProvider')]
    public function testANewEntityIsNotServedAFreedEntitysValues(bool $useHydratorCache): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['useHydratorCache' => $useHydratorCache]));
        $schema = $this->getSchema($driver);
        $query  = '{ performance (filter: { id: { eq: 1 } }) { edges { node { id venue } } } }';

        $before = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertSame('Delta Center', $before['data']['performance']['edges'][0]['node']['venue']);

        // Another process changes the row between requests
        $this->getEntityManager()->getConnection()
            ->executeStatement("UPDATE performance SET venue = 'Changed Venue' WHERE id = 1");
        $this->getEntityManager()->clear();
        gc_collect_cycles();

        $after = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertSame('Changed Venue', $after['data']['performance']['edges'][0]['node']['venue']);
    }

    /**
     * The resolver lives as long as the driver.  The values it has extracted
     * must not outlive the entities they were extracted from, or a long
     * running process retains every entity it has ever resolved.
     */
    public function testExtractedValuesAreReleasedWithTheirEntities(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['useHydratorCache' => true]));

        GraphQL::executeQuery(
            $this->getSchema($driver),
            '{ performance { edges { node { id venue } } } }',
        );

        $resolver       = $driver->get(FieldResolver::class);
        $extractedCount = static fn (): int => count(
            (new ReflectionProperty(FieldResolver::class, 'extractValues'))->getValue($resolver),
        );

        $this->assertSame(10, $extractedCount());

        $this->getEntityManager()->clear();
        gc_collect_cycles();

        $this->assertSame(0, $extractedCount());
    }
}
