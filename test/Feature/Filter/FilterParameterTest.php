<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\QueryBuilder as FilterQueryBuilder;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;

/**
 * Filter parameters are named in order, skipping a name already bound
 */
class FilterParameterTest extends TestCase
{
    private function entity(): Entity
    {
        $entity = (new Driver($this->getEntityManager()))->get(EntityTypeContainer::class)->get(Performance::class);
        $this->assertInstanceOf(Entity::class, $entity);

        return $entity;
    }

    private function queryBuilder(): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('entity')
            ->from(Performance::class, 'entity');
    }

    /** @return array<string, mixed> */
    private function parameters(QueryBuilder $queryBuilder): array
    {
        $parameters = [];
        foreach ($queryBuilder->getParameters() as $parameter) {
            $this->assertInstanceOf(Parameter::class, $parameter);
            $parameters[$parameter->getName()] = $parameter->getValue();
        }

        return $parameters;
    }

    public function testParametersAreNamedInOrder(): void
    {
        $queryBuilder = $this->queryBuilder();

        (new FilterQueryBuilder())->apply(
            [
                'venue' => ['eq' => 'Delta Center', 'contains' => 'Center'],
                'id' => ['between' => ['from' => 1, 'to' => 3]],
            ],
            $queryBuilder,
            $this->entity(),
        );

        $this->assertSame(
            ['filter1' => 'Delta Center', 'filter2' => '%Center%', 'filter3' => 1, 'filter4' => 3],
            $this->parameters($queryBuilder),
        );
        $this->assertCount(1, $queryBuilder->getQuery()->getResult());
    }

    /**
     * A parameter bound before the filters, as by a QueryBuilder event
     * listener, keeps its name and value
     */
    public function testBoundNameIsSkipped(): void
    {
        $queryBuilder = $this->queryBuilder()
            ->andWhere('entity.venue = :filter1')
            ->setParameter('filter1', 'E Center');

        (new FilterQueryBuilder())->apply(
            ['id' => ['between' => ['from' => 1, 'to' => 10]]],
            $queryBuilder,
            $this->entity(),
        );

        $this->assertSame(
            ['filter1' => 'E Center', 'filter2' => 1, 'filter3' => 10],
            $this->parameters($queryBuilder),
        );
        $this->assertCount(2, $queryBuilder->getQuery()->getResult());
    }
}
