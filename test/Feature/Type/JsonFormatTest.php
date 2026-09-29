<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\ConfigBuilder;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Json;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\JsonFormat;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use stdClass;

use function fopen;
use function json_encode;

/**
 * The Json scalar exchanges a JSON value as a string containing a JSON
 * document, the default, or as the value itself
 */
class JsonFormatTest extends TestCase
{
    private function schema(Config $config): Schema
    {
        $driver = new Driver($this->getEntityManager(), $config);
        $json   = $driver->type('json');

        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typeTest' => $driver->completeConnection(TypeTest::class),
                    // Returns its argument, parsed and serialized by the scalar
                    'echo' => [
                        'type' => $json,
                        'args' => ['value' => $json],
                        'resolve' => static fn (mixed $root, array $args): mixed => $args['value'],
                    ],
                ],
            ]),
        ]);
    }

    /**
     * @param array<string, mixed>|null $variables
     *
     * @return mixed[]
     */
    private function execute(Config $config, string $query, array|null $variables = null): array
    {
        $result = GraphQL::executeQuery($this->schema($config), $query, variableValues: $variables)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return $result['data'];
    }

    public function testStringIsTheDefault(): void
    {
        $this->assertSame(JsonFormat::String, (new Config())->getFormatJsonAs());

        $data = $this->execute(
            new Config(['group' => 'DataTypesTest']),
            '{ typeTest { edges { node { testJson } } } echo(value: "{\\"a\\":[1,2]}") }',
        );

        $this->assertSame('{"to":"json","embedded":{"test":"testing"}}', $data['typeTest']['edges'][0]['node']['testJson']);
        $this->assertSame('{"a":[1,2]}', $data['echo']);
    }

    public function testObjectFieldIsTheValue(): void
    {
        $data = $this->execute(
            new Config(['group' => 'DataTypesTest', 'formatJsonAs' => JsonFormat::Object]),
            '{ typeTest { edges { node { testJson } } } }',
        );

        $this->assertSame(['to' => 'json', 'embedded' => ['test' => 'testing']], $data['typeTest']['edges'][0]['node']['testJson']);
    }

    public function testObjectLiteralIsTheValue(): void
    {
        $data = $this->execute(
            new Config(['formatJsonAs' => JsonFormat::Object]),
            '{ echo(value: { a: [1, 2.5, "three", true, null], b: { c: "d" } }) }',
        );

        $this->assertSame(['a' => [1, 2.5, 'three', true, null], 'b' => ['c' => 'd']], $data['echo']);
    }

    public function testObjectVariableIsTheValue(): void
    {
        $value = ['a' => [1, 2], 'b' => 'c'];

        $data = $this->execute(
            new Config(['formatJsonAs' => JsonFormat::Object]),
            'query ($value: Json) { echo(value: $value) }',
            ['value' => $value],
        );

        $this->assertSame($value, $data['echo']);

        // A variable within a literal
        $data = $this->execute(
            new Config(['formatJsonAs' => JsonFormat::Object]),
            'query ($b: Json) { echo(value: { a: 1, b: $b }) }',
            ['b' => 'c'],
        );

        $this->assertSame(['a' => 1, 'b' => 'c'], $data['echo']);
    }

    /**
     * An empty PHP array cannot tell an empty object from an empty list, so it
     * is a list; an object is an object
     */
    public function testEmptyObject(): void
    {
        $json = new Json(JsonFormat::Object);

        $this->assertSame('[]', json_encode($json->serialize([])));
        $this->assertSame('{}', json_encode($json->serialize(new stdClass())));
    }

    public function testObjectValueWhichIsNotJsonIsAnError(): void
    {
        $result = GraphQL::executeQuery(
            new Schema([
                'query' => new ObjectType([
                    'name' => 'query',
                    'fields' => [
                        'stream' => [
                            'type' => new Json(JsonFormat::Object),
                            'resolve' => static fn (): mixed => fopen('php://memory', 'r'),
                        ],
                    ],
                ]),
            ]),
            '{ stream }',
        )->toArray();

        $this->assertArrayHasKey('errors', $result);
        $this->assertNull($result['data']['stream']);
    }

    public function testFormatMayBeGivenByValue(): void
    {
        $this->assertSame(JsonFormat::Object, (new Config(['formatJsonAs' => 'object']))->getFormatJsonAs());
        $this->assertSame(JsonFormat::Object, ConfigBuilder::create()->formatJsonAs(JsonFormat::Object)->build()->getFormatJsonAs());
    }

    public function testUnknownFormatIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration value for formatJsonAs: "xml" is not a JSON format.');

        new Config(['formatJsonAs' => 'xml']);
    }

    public function testFormatOfAnotherTypeIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration value for formatJsonAs: expected ' . JsonFormat::class . ' or string, got int.');

        new Config(['formatJsonAs' => 1]);
    }

    public function testDescriptionNamesTheFormat(): void
    {
        $this->assertStringContainsString('as a string', (string) (new Json())->description);
        $this->assertStringContainsString('as the value itself', (string) (new Json(JsonFormat::Object))->description);
    }
}
