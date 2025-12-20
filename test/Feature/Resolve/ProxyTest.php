<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class ProxyTest extends TestCase
{
    /** @var Schema[] */
    private array $schemas = [];

    /** @return Schema[] */
    public static function schemaProvider(): array
    {
        return [
            'dynamic case' => [
                static function () {
                    $driver = new Driver(self::$entityManager);

                    return new Schema([
                        'query' => new ObjectType([
                            'name' => 'query',
                            'fields' => [
                                'performances' => $driver->completeConnection(Performance::class),
                            ],
                        ]),
                    ]);
                },
            ],
        ];
    }

    /**
     * A proxy object is used for the artist data from the query builder performances.
     * This test assures that the proxy object is properly hydrated.
     */
    #[DataProvider('schemaProvider')]
    public function testProxyObject(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performances ( filter: {id: { eq: 1 } } ) { edges { node { id artist { id name } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals('Grateful Dead', $data['performances']['edges'][0]['node']['artist']['name']);
    }
}
