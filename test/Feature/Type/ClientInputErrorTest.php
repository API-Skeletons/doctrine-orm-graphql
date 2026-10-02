<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A client is told what is wrong with the value it sent, named by the
 * GraphQL type, whether the value is a literal or a variable
 */
class ClientInputErrorTest extends TestCase
{
    /** @return array<string, array{string, string, string}> The type, by its Doctrine name, the value and the message */
    public static function valueProvider(): array
    {
        return [
            'invalid JSON' => ['json', '{bad', 'Json is not a valid JSON document.'],
            'interval too large' => [
                'dateinterval',
                'P99999999999999999999Y',
                'DateInterval P99999999999999999999Y is out of range.',
            ],
            'immutable date and time' => [
                'datetime_immutable',
                'yesterday',
                'DateTimeImmutable format does not match ISO 8601.',
            ],
        ];
    }

    #[DataProvider('valueProvider')]
    public function testClientIsToldWhatIsWrong(string $doctrineType, string $value, string $message): void
    {
        $driver = new Driver($this->getEntityManager());
        $type   = $driver->type($doctrineType);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'echo' => [
                        'type' => Type::string(),
                        'args' => ['value' => $type],
                        'resolve' => static fn (): string => 'ok',
                    ],
                ],
            ]),
        ]);

        $literal = GraphQL::executeQuery($schema, '{ echo(value: "' . $value . '") }')->toArray();
        $this->assertSame($message, $literal['errors'][0]['message']);

        $variable = GraphQL::executeQuery(
            $schema,
            'query ($value: ' . $type->name() . ') { echo(value: $value) }',
            variableValues: ['value' => $value],
        )->toArray();
        $this->assertStringContainsString($message, $variable['errors'][0]['message']);
    }
}
