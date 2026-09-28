<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Trait;

use Doctrine\ORM\QueryBuilder;

/**
 * Order a query by its root entity's identifier, after any other ordering
 *
 * Without an ORDER BY, SQL does not guarantee the order of rows, so pages
 * fetched with an offset may overlap or skip rows.  Appending the identifier
 * makes the order total: it decides the order when nothing else does and
 * breaks ties between rows which sort equally.
 */
trait OrderByIdentifier
{
    /**
     * Add the identifier as the last ordering.  Call this after anything
     * else, such as a QueryBuilder event listener, has ordered the query.
     */
    private function orderByIdentifier(QueryBuilder $queryBuilder, string $alias = 'entity'): void
    {
        $rootEntity = $queryBuilder->getRootEntities()[0];
        $identifier = $queryBuilder->getEntityManager()->getClassMetadata($rootEntity)->getIdentifierFieldNames();

        foreach ($identifier as $fieldName) {
            $queryBuilder->addOrderBy($alias . '.' . $fieldName, 'ASC');
        }
    }
}
