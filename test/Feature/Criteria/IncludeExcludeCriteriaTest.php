<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Criteria;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Throwable;

class IncludeExcludeCriteriaTest extends AbstractTest
{
    public function testExcludeCriteria(): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('includeCriteria and excludeCriteria are mutually exclusive.');

        $config = new Config(['group' => 'IncludeExcludeCriteriaTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performances' => [
                        'type' => $driver->connection($driver->type(Performance::class)),
                        'args' => [
                            'filter' => $driver->filter(Performance::class),
                            'pagination' => $driver->pagination(),
                        ],
                        'resolve' => $driver->resolve(Performance::class),
                    ],
                ],
            ]),
        ]);
    }
}
