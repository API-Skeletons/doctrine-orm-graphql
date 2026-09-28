<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Json;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;

class JsonTest extends TestCase
{
    public function testParseValue(): void
    {
        $jsonType = new Json();

        $control = ['array' => 'test', 'in' => ['json']];
        $result  = $jsonType->parseValue('{"array": "test", "in": ["json"]}');

        $this->assertEquals($control, $result);
    }

    public function testParseValueInvalidNull(): void
    {
        $this->expectException(Error::class);

        $jsonType = new Json();
        $result   = $jsonType->parseValue(null);
    }

    public function testParseValueInvalidJson(): void
    {
        $this->expectException(Error::class);

        $jsonType = new Json();
        $result   = $jsonType->parseValue('{"field": "value}');
    }

    public function testParseLiteral(): void
    {
        $jsonType    = new Json();
        $node        = new StringValueNode([]);
        $node->value = '{"field": "value"}';
        $result      = $jsonType->parseLiteral($node);

        $this->assertEquals(['field' => 'value'], $result);
    }

    /**
     * Every JSON document is valid input, not only objects and arrays
     *
     * @return array<string, array{string, mixed}>
     */
    public static function jsonDocumentProvider(): array
    {
        return [
            'object' => ['{"a": 1}', ['a' => 1]],
            'array' => ['[1, 2]', [1, 2]],
            'null' => ['null', null],
            'integer' => ['5', 5],
            'float' => ['1.5', 1.5],
            'string' => ['"text"', 'text'],
            'true' => ['true', true],
            'false' => ['false', false],
        ];
    }

    #[DataProvider('jsonDocumentProvider')]
    public function testParseValueAcceptsEveryJsonDocument(string $json, mixed $expected): void
    {
        $this->assertSame($expected, (new Json())->parseValue($json));
    }

    public function testParseValueReportsTheTypeOfANonString(): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('JSON is not a string: array');

        (new Json())->parseValue(['a' => 1]);
    }

    public function testParseValueReportsTheJsonError(): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('Could not parse JSON data: Syntax error');

        (new Json())->parseValue('{bad');
    }

    public function testParseLiteralRejectsANonStringLiteral(): void
    {
        $node        = new IntValueNode([]);
        $node->value = '5';

        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('Query error: Can only parse strings got: IntValue');

        (new Json())->parseLiteral($node);
    }

    public function testSerializeFails(): void
    {
        $this->expectException(Error::class);

        $jsonType = new Json();
        $jsonType->serialize(['name' => "\xB1\x31"]);
    }

    /**
     * A filter value is decoded JSON, which does not compare to the stored JSON
     * text, so a JSON field has only the isnull filter
     */
    public function testOnlyIsnullFilter(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DataTypesTest']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typetest' => $driver->completeConnection(TypeTest::class),
                ],
            ]),
        ]);

        $filter = $driver->filter(TypeTest::class);
        $this->assertInstanceOf(InputObjectType::class, $filter);
        $testJson = $filter->getField('testJson')->getType();
        $this->assertInstanceOf(InputObjectType::class, $testJson);
        $this->assertSame(['isnull'], array_keys($testJson->getFields()));

        foreach (['false' => 1, 'true' => 0] as $isnull => $count) {
            $query  = '{ typetest ( filter: { testJson: { isnull: ' . $isnull . ' } } ) { edges { node { id } } } }';
            $result = GraphQL::executeQuery($schema, $query)->toArray();

            $this->assertArrayNotHasKey('errors', $result);
            $this->assertCount($count, $result['data']['typetest']['edges']);
        }

        $query  = '{ typetest ( filter: { testJson: { eq: "{}" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertSame('Field "eq" is not defined by type "' . $testJson->name() . '".', $result['errors'][0]['message']);
    }

    /**
     * A field whose filters are all excluded has no filter field; an input
     * object must have a field
     */
    public function testFieldWithoutFiltersIsOmitted(): void
    {
        $driver = new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'DataTypesTest', 'excludeFilters' => [Filters::ISNULL]]),
        );

        $filter = $driver->filter(TypeTest::class);
        $this->assertInstanceOf(InputObjectType::class, $filter);
        $this->assertNull($filter->findField('testJson'));
        $this->assertNotNull($filter->findField('testText'));
    }
}
