<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedArgsArtist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedArgsInvalid;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedArgsRecording;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use DateTimeImmutable;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

use function assert;
use function is_array;

/**
 * A computed field has an argument for each parameter of its method
 */
class ComputedFieldArgumentsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();

        $artist = new ComputedArgsArtist('Phish', new DateTimeImmutable('1983-12-02'));
        $entityManager->persist($artist);

        foreach ([['Junta', 2002], ['Lawn Boy', 2002], ['Round Room', 2003], ['Undermind', 2004]] as [$title, $year]) {
            $entityManager->persist(new ComputedArgsRecording($title, $year, $artist));
        }

        $entityManager->flush();
        $entityManager->clear();
    }

    private function getDriver(bool $useHydratorCache = false): Driver
    {
        return new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'ComputedArgs', 'useHydratorCache' => $useHydratorCache]),
        );
    }

    private function getSchema(Driver $driver): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['artists' => $driver->completeConnection(ComputedArgsArtist::class)],
            ]),
        ]);
    }

    /**
     * The fields of the one artist
     *
     * @return array<string, mixed>
     */
    private function queryArtist(string $fields, bool $useHydratorCache = false): array
    {
        $this->getEntityManager()->clear();

        $result = GraphQL::executeQuery(
            $this->getSchema($this->getDriver($useHydratorCache)),
            '{ artists { edges { node { ' . $fields . ' } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        return $result['data']['artists']['edges'][0]['node'];
    }

    public function testSchemaIsValid(): void
    {
        $this->getSchema($this->getDriver())->assertValid();
        $this->expectNotToPerformAssertions();
    }

    public function testArgumentsAreTheMethodsParameters(): void
    {
        $artist = $this->getDriver()->type(ComputedArgsArtist::class);
        assert($artist instanceof ObjectType);

        // A parameter which allows null is a nullable argument
        $year = $artist->getField('totalRecordings')->getArg('year');
        $this->assertSame(Type::int(), $year?->getType());
        $this->assertFalse($year->defaultValueExists());

        // A parameter which does not is non-null
        $this->assertSame('String!', $artist->getField('greeting')->getArg('salutation')?->getType()->toString());

        // A default value is the argument's
        $times = $artist->getField('repeatedName')->getArg('times');
        $this->assertSame('Int!', $times?->getType()->toString());
        $this->assertSame(2, $times->defaultValue);
        $this->assertSame(' ', $artist->getField('repeatedName')->getArg('separator')?->defaultValue);
        $this->assertSame(1.5, $artist->getField('scaled')->getArg('factor')?->defaultValue);
        $this->assertFalse($artist->getField('shout')->getArg('loud')?->defaultValue);
        $this->assertSame('Boolean!', $artist->getField('shout')->getArg('loud')->getType()->toString());

        // A type given in the attribute's args
        $this->assertSame('DateImmutable!', $artist->getField('foundedBefore')->getArg('date')?->getType()->toString());

        // A field of an entity type has arguments too
        $this->assertSame('Int!', $artist->getField('recordingsSince')->getArg('year')?->getType()->toString());

        $this->assertSame([], $artist->getField('displayName')->args);
    }

    /**
     * The field with and without its optional argument
     */
    public function testOptionalArgument(): void
    {
        $this->assertSame(
            ['name' => 'Phish', 'totalRecordings' => 4],
            $this->queryArtist('name totalRecordings'),
        );

        $this->assertSame(
            ['name' => 'Phish', 'totalRecordings' => 2],
            $this->queryArtist('name totalRecordings(year: 2002)'),
        );
    }

    /** @return array<string, array{bool}> */
    public static function hydratorCacheProvider(): array
    {
        return [
            'hydrator cache' => [true],
            'no hydrator cache' => [false],
        ];
    }

    /**
     * Aliases of a field with different arguments each have their own value
     */
    #[DataProvider('hydratorCacheProvider')]
    public function testAliasesWithDifferentArguments(bool $useHydratorCache): void
    {
        $this->assertSame(
            ['a' => 2, 'b' => 1, 'c' => 4, 'd' => 2, 'e' => 0],
            $this->queryArtist(
                'a: totalRecordings(year: 2002) b: totalRecordings(year: 2003) c: totalRecordings '
                . 'd: totalRecordings(year: 2002) e: totalRecordings(year: 1999)',
                $useHydratorCache,
            ),
        );
    }

    public function testDefaultValues(): void
    {
        $this->assertSame(
            [
                'repeatedName' => 'Phish Phish',
                'three' => 'Phish Phish Phish',
                'dashed' => 'Phish-Phish',
                'scaled' => 6.0,
                'doubled' => 8.0,
                'shout' => 'Phish',
                'loud' => 'PHISH',
            ],
            $this->queryArtist(
                'repeatedName three: repeatedName(times: 3) dashed: repeatedName(separator: "-") '
                . 'scaled doubled: scaled(factor: 2) shout loud: shout(loud: true)',
            ),
        );
    }

    public function testRequiredArgument(): void
    {
        $this->assertSame(['greeting' => 'Hello, Phish'], $this->queryArtist('greeting(salutation: "Hello")'));

        $result = GraphQL::executeQuery(
            $this->getSchema($this->getDriver()),
            '{ artists { edges { node { greeting } } } }',
        )->toArray();

        $this->assertSame(
            'Field "greeting" argument "salutation" of type "String!" is required but not provided.',
            $result['errors'][0]['message'],
        );
    }

    public function testArgumentsGivenAsVariables(): void
    {
        $result = GraphQL::executeQuery(
            $this->getSchema($this->getDriver()),
            'query ($year: Int) { artists { edges { node { totalRecordings(year: $year) } } } }',
            null,
            null,
            ['year' => 2004],
        )->toArray();

        $this->assertSame(1, $result['data']['artists']['edges'][0]['node']['totalRecordings']);
    }

    public function testListOfEntitiesWithAnArgument(): void
    {
        $this->assertSame(
            [['title' => 'Round Room'], ['title' => 'Undermind']],
            $this->queryArtist('recordingsSince(year: 2003) { title }')['recordingsSince'],
        );
    }

    public function testArgumentOfAGivenType(): void
    {
        $this->assertSame(
            ['before' => true, 'after' => false],
            $this->queryArtist(
                'before: foundedBefore(date: "1990-01-01") after: foundedBefore(date: "1980-01-01")',
            ),
        );
    }

    /**
     * A computed field with arguments has no one value, so the hydrator's
     * extract() leaves it out
     */
    public function testExtractLeavesOutFieldsWithArguments(): void
    {
        $driver = $this->getDriver();
        $artist = $this->getEntityManager()->getRepository(ComputedArgsArtist::class)->findOneBy([]);
        assert($artist instanceof ComputedArgsArtist);

        $extracted = $driver->get(EntityTypeContainer::class)->get(ComputedArgsArtist::class)
            ->getHydrator()->extract($artist);

        $this->assertSame('The Phish', $extracted['displayName']);
        $this->assertArrayNotHasKey('totalRecordings', $extracted);
        $this->assertArrayNotHasKey('greeting', $extracted);
    }

    public function testMetadataRecordsTheArguments(): void
    {
        $metadata = $this->getDriver()->get(Metadata::class)->toArray();
        $computed = $metadata[ComputedArgsArtist::class]['computedFields'];

        $this->assertSame(['year' => ['type' => 'int', 'nullable' => true]], $computed['totalRecordings']['args']);
        $this->assertSame(
            [
                'times' => ['type' => 'int', 'nullable' => false, 'default' => 2],
                'separator' => ['type' => 'string', 'nullable' => false, 'default' => ' '],
            ],
            $computed['repeatedName']['args'],
        );
        $this->assertSame(['date' => ['type' => 'date_immutable', 'nullable' => false]], $computed['foundedBefore']['args']);
        $this->assertSame([], $computed['displayName']['args']);

        // Cached metadata builds the same types
        $cached = new Driver($this->getEntityManager(), new Config(['group' => 'ComputedArgs']), $metadata);
        $artist = $cached->type(ComputedArgsArtist::class);
        assert($artist instanceof ObjectType);
        $this->assertSame(2, $artist->getField('repeatedName')->getArg('times')?->defaultValue);
    }

    public function testDefaultInTheMetadataMustBeScalar(): void
    {
        $driver = $this->getDriver();

        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event): void {
                $metadata = $event->getMetadata();
                $artist   = $metadata[ComputedArgsArtist::class];
                assert(is_array($artist));

                $artist['computedFields']['repeatedName']['args']['times']['default'] = [2];
                $metadata[ComputedArgsArtist::class]                                  = $artist;
            },
        );

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage(
            'Metadata for entity ' . ComputedArgsArtist::class . ' computed field repeatedName argument times key '
            . 'default must be an int, float, string or bool, array given.',
        );

        $driver->type(ComputedArgsArtist::class);
    }

    /**
     * An argument's name must be a valid GraphQL name.  A parameter may be
     * named so it is not, such as $__value, as may an argument a listener adds.
     */
    public function testInvalidArgumentNameIsRejected(): void
    {
        $driver = $this->getDriver();

        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event): void {
                $metadata = $event->getMetadata();
                $artist   = $metadata[ComputedArgsArtist::class];
                assert(is_array($artist));

                $artist['computedFields']['greeting']['args'] = ['__value' => ['type' => 'string', 'nullable' => false]];
                $metadata[ComputedArgsArtist::class]          = $artist;
            },
        );

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage(
            'Metadata for entity ' . ComputedArgsArtist::class . ': the name of argument __value of computed field '
            . 'greeting, "__value", is not a valid GraphQL name.',
        );

        $driver->type(ComputedArgsArtist::class);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidProvider(): array
    {
        $method = static fn (string $parameter, string $method): string => 'Parameter $' . $parameter
            . ' of computed field method ' . $method . ' of entity ' . ComputedArgsInvalid::class;

        return [
            'unsupported type' => [
                'ComputedArgsUnsupportedType',
                $method('values', 'getUnsupportedType') . ' is not an int, float, string or bool.  Give its type in '
                . 'the args of the ComputedField attribute.',
            ],
            'union type' => [
                'ComputedArgsUnionType',
                $method('value', 'getUnionType') . ' is not an int, float, string or bool.  Give its type in the '
                . 'args of the ComputedField attribute.',
            ],
            'variadic' => [
                'ComputedArgsVariadic',
                $method('values', 'getVariadic') . ' is variadic or passed by reference, which an argument cannot be.',
            ],
            'by reference' => [
                'ComputedArgsByReference',
                $method('value', 'getByReference') . ' is variadic or passed by reference, which an argument cannot be.',
            ],
            'object default' => [
                'ComputedArgsObjectDefault',
                $method('date', 'getObjectDefault') . ' has a default value which is not an int, float, string or '
                . 'bool, which an argument cannot have.',
            ],
            'unknown argument' => [
                'ComputedArgsUnknownArg',
                'The args of the ComputedField attribute of computed field method getUnknownArg of entity '
                . ComputedArgsInvalid::class . ' give the type of other, which is not a parameter of the method.',
            ],
            'not an input type' => [
                'ComputedArgsNotInput',
                'Argument page of computed field notInput of entity ' . ComputedArgsInvalid::class
                . ' is of type PageInfo, which cannot be input.',
            ],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testParameterWhichCannotBeAnArgumentIsAnError(string $group, string $message): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => $group]));

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage($message);

        $driver->type(ComputedArgsInvalid::class);
    }
}
