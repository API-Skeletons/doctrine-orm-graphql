<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\FilterCriteria;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use Doctrine\Common\Collections\Criteria;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;

use function count;

class FilterCriteriaTest extends AbstractTest
{
    public function testEvent(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'FilterCriteriaEvent']));

        $driver->get(EventDispatcher::class)->subscribeTo(
            Artist::class . '.performances.filterCriteria',
            function (FilterCriteria $event): void {
                $this->assertInstanceOf(Criteria::class, $event->getCriteria());

                $event->getCriteria()->andWhere(
                    $event->getCriteria()->expr()->eq('venue', 'Delta Center'),
                );

                $this->assertInstanceOf(Artist::class, $event->getObjectValue());
                $this->assertEquals('contextTest', $event->getContext());
                $this->assertIsArray($event->getArgs());
                $this->assertInstanceOf(ResolveInfo::class, $event->getInfo());
            },
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection($driver->type(Artist::class)),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '{
            artist (filter: { id: { eq: 1 } } ) {
              edges {
                node {
                  id
                  name
                  performances {
                    edges {
                      node {
                        venue
                      }
                    }
                  }
                }
              }
            }
        }';

        $result = GraphQL::executeQuery($schema, $query, null, 'contextTest');
        $data   = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artist']['edges']));
        $this->assertEquals(1, count($data['artist']['edges'][0]['node']['performances']));
        $this->assertEquals(
            'Delta Center',
            $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['venue'],
        );
    }
}
