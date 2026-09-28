<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use GraphQL\Type\Definition\Type;

/**
 * This class manages all GraphQL types
 */
final class TypeContainer extends Container
{
    /**
     * A Doctrine type which maps to the same GraphQL type as another shares
     * its instance; a schema may have only one type of each name.
     */
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
            ->set('smallfloat', static fn () => Type::float())
            ->set('number', static fn () => Type::string())
            ->set('string', static fn () => Type::string())
            ->set('ascii_string', static fn () => Type::string())
            ->set('guid', static fn () => Type::string())
            ->set('enum', static fn () => Type::string())
            ->set('text', static fn () => Type::string())
            ->set('simple_array', static fn () => Type::listOf(Type::string()))
            ->set('json', static fn () => new Json())
            ->set('json_object', static fn (Container $container): mixed => $container->get('json'))
            ->set('jsonb', static fn (Container $container): mixed => $container->get('json'))
            ->set('jsonb_object', static fn (Container $container): mixed => $container->get('json'))
            ->set('date', static fn () => new Date())
            ->set('datetime', static fn () => new DateTime())
            ->set('datetime_utc', static fn (Container $container): mixed => $container->get('datetime'))
            ->set('datetimetz', static fn () => new DateTimeTZ())
            ->set('time', static fn () => new Time())
            ->set('dateinterval', static fn () => new DateInterval())
            ->set('date_immutable', static fn () => new DateImmutable())
            ->set('datetime_immutable', static fn () => new DateTimeImmutable())
            ->set('datetime_utc_immutable', static fn (Container $container): mixed => $container->get('datetime_immutable'))
            ->set('datetimetz_immutable', static fn () => new DateTimeTZImmutable())
            ->set('time_immutable', static fn () => new TimeImmutable())
            ->set('pageinfo', static fn () => new PageInfo())
            ->set('sortdirection', static fn () => new SortDirection())
            ->set('blob', static fn () => new Blob())
            ->set('binary', static fn (Container $container): mixed => $container->get('blob'));
    }
}
