<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class ProxyTest extends AbstractTest
{
    /** @var Schema[] */
    private array $schemas = [];

    /** @return Schema[] */
    public function schemaProvider(): array
    {
        parent::setUp();

        $schemas = [];

        $driver    = new Driver($this->getEntityManager());
        $schemas[] = [
            new Schema([
                'query' => new ObjectType([
                    'name' => 'query',
                    'fields' => [
                        'performances' => $driver->completeConnection(Performance::class),
                    ],
                ]),
            ]),
        ];

        return $schemas;
    }

    /**
     * A proxy object is used for the artist data from the query builder performances.
     * This test assures that the proxy object is properly hydrated.
     *
     * @dataProvider schemaProvider
     */
    public function testProxyObject(Schema $schema): void
    {
        $query  = '{ performances ( filter: {id: { eq: 1 } } ) { edges { node { id artist { id name } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals('Grateful Dead', $data['performances']['edges'][0]['node']['artist']['name']);
    }
}
