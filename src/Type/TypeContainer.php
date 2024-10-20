<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use GraphQL\Type\Definition\Type;

/**
 * This class manages all GraphQL types
 */
class TypeContainer extends Container
{
    public function __construct()
    {
        $this
            ->set('tinyint', static fn () => Type::int())
            ->set('smallint', static fn () => Type::int())
            ->set('integer', static fn () => Type::int())
            ->set('int', static fn () => Type::int())
            ->set('boolean', static fn () => Type::boolean())
            ->set('decimal', static fn () => Type::float())
            ->set('float', static fn () => Type::float())
            ->set('bigint', static fn () => Type::string())
            ->set('string', static fn () => Type::string())
            ->set('text', static fn () => Type::string())
            ->set('simple_array', static fn () => Type::listOf(Type::string()))
            ->set('json', static fn () => new Json())
            ->set('date', static fn () => new Date())
            ->set('datetime', static fn () => new DateTime())
            ->set('datetimetz', static fn () => new DateTimeTZ())
            ->set('time', static fn () => new Time())
            ->set('date_immutable', static fn () => new DateImmutable())
            ->set('datetime_immutable', static fn () => new DateTimeImmutable())
            ->set('datetimetz_immutable', static fn () => new DateTimeTZImmutable())
            ->set('time_immutable', static fn () => new TimeImmutable())
            ->set('pageinfo', static fn () => new PageInfo())
            ->set('pagination', static fn () => new Pagination())
            ->set('blob', static fn () => new Blob());
    }
}
