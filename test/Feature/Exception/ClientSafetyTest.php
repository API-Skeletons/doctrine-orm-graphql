<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Exception;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\GraphQL as GraphQLException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Pagination;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeNotFound;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithoutGetter;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Exception;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * A client sees the message of an error its request causes, and only
 * "Internal server error" for an error the developer must fix, which may
 * name classes, methods and groups
 */
class ClientSafetyTest extends TestCase
{
    /** @return array<string, array{GraphQLException, bool}> */
    public static function exceptionProvider(): array
    {
        return [
            'Configuration' => [new Configuration('message'), false],
            'Hydrator' => [new Hydrator('message'), false],
            'Input' => [new Input('message'), false],
            'Metadata' => [new Metadata('message'), false],
            'TypeNotFound' => [new TypeNotFound('type'), false],
            'Filter' => [new Filter('message'), true],
            'Pagination' => [new Pagination('message'), true],
            'TypeSerialization' => [new TypeSerialization('message'), true],
            'client error of an exception' => [new TypeSerialization('message', null, new Exception('internal')), false],
            'client error of a client error' => [new TypeSerialization('message', null, new Filter('message')), true],
        ];
    }

    #[DataProvider('exceptionProvider')]
    public function testClientSafety(GraphQLException $exception, bool $clientSafe): void
    {
        $this->assertSame($clientSafe, $exception->isClientSafe());
    }

    private function execute(Schema $schema, string $query): ExecutionResult
    {
        return GraphQL::executeQuery($schema, $query);
    }

    /** A schema whose fields are built when a query is validated */
    private function lazySchema(Driver $driver, string $entityClass): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => static fn (): array => ['entities' => $driver->completeConnection($entityClass)],
            ]),
        ]);
    }

    private function schema(Driver $driver, string $entityClass): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['entities' => $driver->completeConnection($entityClass)],
            ]),
        ]);
    }

    /** @param callable(Metadata): void $change */
    private function changeMetadata(Driver $driver, callable $change): void
    {
        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event) use ($change): void {
                $change($event->getMetadata());
            },
        );
    }

    /**
     * The client sees "Internal server error"; the developer's message is in
     * the result's errors, for logging, and in the debug message
     */
    private function assertHidden(ExecutionResult $result, string $message): void
    {
        $this->assertSame('Internal server error', $result->toArray()['errors'][0]['message']);
        $this->assertStringContainsString($message, $result->errors[0]->getMessage());
        $this->assertStringContainsString(
            $message,
            $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)['errors'][0]['extensions']['debugMessage'],
        );
    }

    public function testEntityErrorWhileValidatingIsHidden(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'MissingGetter']));

        $this->assertHidden(
            $this->execute($this->lazySchema($driver, TestEntityWithoutGetter::class), '{ entities { edges { node { id } } } }'),
            'Field name of entity ' . TestEntityWithoutGetter::class . ' has no getName()',
        );
    }

    /**
     * An association's target type is built when a query is validated, even
     * when the schema is built first
     */
    public function testMetadataErrorOfAnAssociationTargetIsHidden(): void
    {
        $driver = new Driver($this->getEntityManager());
        $this->changeMetadata($driver, static function ($metadata): void {
            $artist                  = $metadata[Artist::class];
            $artist['limit']         = -1;
            $metadata[Artist::class] = $artist;
        });

        $this->assertHidden(
            $this->execute($this->schema($driver, Performance::class), '{ entities { edges { node { artist { name } } } } }'),
            'Metadata for entity ' . Artist::class . ' key limit must be at least 0',
        );
    }

    /** A hydrator is built when an entity is first extracted */
    public function testHydratorErrorWhileExecutingIsHidden(): void
    {
        $driver = new Driver($this->getEntityManager());
        $this->changeMetadata($driver, static function ($metadata): void {
            $performance                                        = $metadata[Performance::class];
            $performance['fields']['venue']['hydratorStrategy'] = stdClass::class;
            $metadata[Performance::class]                       = $performance;
        });

        $this->assertHidden(
            $this->execute($this->schema($driver, Performance::class), '{ entities(first: 1) { edges { node { venue } } } }'),
            'Hydrator strategy stdClass for field venue of entity ' . Performance::class,
        );
    }

    /** The message of a type which is not registered lists every type */
    public function testTypeNotFoundIsHidden(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'CustomTypeTest']));

        $this->assertHidden(
            $this->execute($this->lazySchema($driver, TypeTest::class), '{ entities { edges { node { testFloat } } } }'),
            'Available types:',
        );
    }

    public function testInputErrorWhileValidatingIsHidden(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));
        $schema = new Schema([
            'query' => new ObjectType(['name' => 'query', 'fields' => ['ok' => Type::string()]]),
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => static fn (): array => [
                    'update' => [
                        'type' => Type::string(),
                        'args' => ['input' => $driver->input(User::class, ['nmae'])],
                        'resolve' => static fn (): string => 'updated',
                    ],
                ],
            ]),
        ]);

        $this->assertHidden(
            $this->execute($schema, 'mutation { update(input: { name: "name" }) }'),
            'Field nmae is not a field of entity ' . User::class,
        );
    }

    /** @return array<string, array{string, string}> */
    public static function clientErrorProvider(): array
    {
        return [
            'filter' => [
                '{ entities(filter: { venue: { eq: null } }) { edges { node { id } } } }',
                "Filter 'eq' of field 'venue' cannot be null.  Use the 'isnull' filter to match null values.",
            ],
            'pagination' => [
                '{ entities(after: "zz") { edges { node { id } } } }',
                'Pagination argument "after" is not a valid cursor.',
            ],
            'scalar' => [
                '{ entities(filter: { performanceDate: { eq: "yesterday" } }) { edges { node { id } } } }',
                'datetime format does not match ISO 8601.',
            ],
        ];
    }

    #[DataProvider('clientErrorProvider')]
    public function testClientErrorIsShown(string $query, string $message): void
    {
        $driver = new Driver($this->getEntityManager());

        $this->assertSame(
            $message,
            $this->execute($this->schema($driver, Performance::class), $query)->toArray()['errors'][0]['message'],
        );
    }
}
