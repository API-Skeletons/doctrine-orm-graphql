<?php

namespace ApiSkeletons\Doctrine\GraphQL\Hydrator\Strategy;

use ApiSkeletons\Doctrine\GraphQL\Invokable;
use Laminas\Hydrator\Strategy\StrategyInterface;

/**
 * Nullify an association.
 *
 * In a many to many relationship from a known starting point it is possible
 * to backwards-query the owning relationship to gather data the user should
 * not be privileged to.
 *
 * For instance in a User <> Role relationship a user may have many roles.  But
 * a role may have many users.  So in a query where a user is fetched then their
 * roles are fetched you could then reverse the query to fetch all users with the
 * same role
 *
 * This query would return all user names with the same roles as the user who
 * created the artist.
 * { artist { user { role { user { name } } } } }
 *
 * This hydrator strategy is used to prevent the reverse lookup by nullifying
 * the response when queried from the owning side of a many to many relationship
 *
 * Ideally the developer will add the owning relation to a filter so the
 * field is not queryable at all.  This strategy exists as a patch for generating
 * a configuration skeleton.
 */
class NullifyOwningAssociation implements
    StrategyInterface,
    Invokable
{
    public function extract($value, ?object $object = null)
    {
        return null;
    }

    /**
     * @codeCoverageIgnore
     */
    public function hydrate($value, ?array $data)
    {
        return null;
    }
}
