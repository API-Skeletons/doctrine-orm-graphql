<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Association;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Recording;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class InversedByCollectionTest extends TestCase
{
    /**
     * Test querying a ManyToMany collection from the owning side (inversedBy)
     */
    public function testUserRecordingsCollection(): void
    {
        $config = new Config(['group' => 'CustomFieldStrategyTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'user' => $driver->completeConnection(User::class),
                    'recording' => $driver->completeConnection(Recording::class),
                ],
            ]),
        ]);

        // Query users and their recordings
        $query = '{
            user(pagination: { first: 5 }) {
                edges {
                    node {
                        name
                        recordings(pagination: { first: 10 }) {
                            edges {
                                node {
                                    source
                                }
                            }
                            totalCount
                        }
                    }
                }
                totalCount
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->assertArrayNotHasKey('errors', $output);
        $this->assertIsArray($output['data']['user']['edges']);
    }

    /**
     * Test querying with filters on the inversedBy collection
     */
    public function testUserRecordingsWithFilters(): void
    {
        $config = new Config(['group' => 'CustomFieldStrategyTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'user' => $driver->completeConnection(User::class),
                ],
            ]),
        ]);

        // Query users with filtered recordings
        $query = '{
            user(pagination: { first: 1 }) {
                edges {
                    node {
                        name
                        recordings(
                            filter: { source: { contains: "tape" } }
                            pagination: { first: 5 }
                        ) {
                            edges {
                                node {
                                    source
                                }
                            }
                        }
                    }
                }
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->assertArrayNotHasKey('errors', $output);
    }
}
