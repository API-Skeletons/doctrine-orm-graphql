<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestToOneFilters;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function array_keys;

/**
 * A to-one association's excludeFilters and includeFilters limit its own
 * filters, by the identifier of the entity it refers to
 */
class ToOneAssociationFiltersTest extends TestCase
{
    private function driver(): Driver
    {
        return new Driver($this->getEntityManager(), new Config(['group' => 'ToOneFilters']));
    }

    public function testAssociationLimitsItsFilters(): void
    {
        $filter = $this->driver()->filter(TestToOneFilters::class);

        // An association left with no filter has no filter field
        $this->assertSame(['id', 'excluded', 'included', 'every'], array_keys($filter->getFields()));

        $filters = static function (string $fieldName) use ($filter): array {
            $type = $filter->getField($fieldName)->getType();
            self::assertInstanceOf(InputObjectType::class, $type);

            return array_keys($type->getFields());
        };

        $this->assertSame(['neq', 'notin', 'isnull'], $filters('excluded'));
        $this->assertSame(['isnull'], $filters('included'));
        $this->assertSame(['eq', 'neq', 'in', 'notin', 'isnull'], $filters('every'));
    }

    public function testExcludedFilterIsNotAvailable(): void
    {
        $entityManager = $this->getEntityManager();
        $first         = new TestToOneFilters();
        $second        = (new TestToOneFilters())->setExcluded($first);
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->flush();
        $entityManager->clear();

        $driver = $this->driver();
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['entities' => $driver->completeConnection(TestToOneFilters::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ entities(filter: { excluded: { isnull: false } }) { edges { node { id } } } }',
        )->toArray();
        $this->assertSame([['node' => ['id' => $second->getId()]]], $result['data']['entities']['edges']);

        $result = GraphQL::executeQuery(
            $schema,
            '{ entities(filter: { excluded: { eq: ' . $first->getId() . ' } }) { edges { node { id } } } }',
        )->toArray();
        $this->assertStringContainsString('Field "eq" is not defined', $result['errors'][0]['message']);
    }
}
