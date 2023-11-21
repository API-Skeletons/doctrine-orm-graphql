<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Blob;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function base64_decode;
use function base64_encode;
use function count;
use function file_get_contents;

class BlobTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $blobType = new Blob();

        $file = file_get_contents(__DIR__ . '/../../../banner.png');

        $encoded = base64_encode($file);
        $result  = $blobType->parseValue($encoded);

        $this->assertEquals($file, $result);
    }

    public function testParseValueInvalid(): void
    {
        $this->expectException(Error::class);

        $jsonType = new Blob();
        $result   = $jsonType->parseValue(true);
    }

    public function testParseLiteral(): void
    {
        $this->expectException(Error::class);

        $jsonType    = new Blob();
        $node        = new StringValueNode([]);
        $node->value = 'search string';
        $result      = $jsonType->parseLiteral($node);
    }

    public function testSerialize(): void
    {
        $blobType = new Blob();

        $file = file_get_contents(__DIR__ . '/../../../banner.png');

        $encoded = base64_encode($file);
        $result  = $blobType->serialize($file);

        $this->assertEquals($encoded, $result);
    }

    public function testBlobQuery(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DataTypesTest']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typetest' => [
                        'type' => $driver->connection($driver->type(TypeTest::class)),
                        'args' => [
                            'filter' => $driver->filter(TypeTest::class),
                        ],
                        'resolve' => $driver->resolve(TypeTest::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ typetest { edges { node { id testBlob } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $file    = base64_decode($data['typetest']['edges'][0]['node']['testBlob']);
        $control = file_get_contents(__DIR__ . '/../../../banner.png');

        $this->assertEquals($file, $control);

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }
}
