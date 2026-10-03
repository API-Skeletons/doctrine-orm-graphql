<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithMagicCall;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithoutGetter;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestGetterOptionalParameter;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestGetterRequiredParameter;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Extracting by value, a field without a getter would always be null, so it
 * is an error when the entity's type is built
 */
class MissingGetterTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->getEntityManager()->persist(new TestEntityWithoutGetter());
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
    }

    /** @return mixed[] */
    private function execute(Config $config, string $fields): array
    {
        $driver = new Driver($this->getEntityManager(), $config);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['entities' => $driver->completeConnection(TestEntityWithoutGetter::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ entities { edges { node { ' . $fields . ' } } } }')->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return $result['data']['entities']['edges'][0]['node'];
    }

    public function testFieldWithoutGetterIsAnError(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'MissingGetter']));

        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage(
            'Field name of entity ' . TestEntityWithoutGetter::class . ' has no getName() or isName() method, which '
            . 'extracting by value reads it with.  Add one, or extract the entity by reference with extractByValue: false.',
        );

        $driver->type(TestEntityWithoutGetter::class);
    }

    public function testFieldWithoutGetterIsExtractedByReference(): void
    {
        $this->assertSame(
            ['name' => 'no getter'],
            $this->execute(new Config(['group' => 'MissingGetterByReference']), 'name'),
        );
    }

    public function testConfigExtractByReference(): void
    {
        $this->assertSame(
            ['name' => 'no getter'],
            $this->execute(new Config(['group' => 'MissingGetter', 'extractByValue' => false]), 'name'),
        );
    }

    /**
     * isField(), and a method of the field's own name for a field named isField
     */
    public function testEveryAccessorTheHydratorReads(): void
    {
        $this->assertSame(
            ['enabled' => true, 'isActive' => true],
            $this->execute(new Config(['group' => 'AccessorForms']), 'enabled isActive'),
        );
    }

    /**
     * A field without a getter is read through __call
     */
    public function testEntityWithCallIsExtractedByValue(): void
    {
        $this->getEntityManager()->persist(
            (new TestEntityWithMagicCall())->setRegularField('regular')->setMagicField('magic'),
        );
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'MagicCall']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['entities' => $driver->completeConnection(TestEntityWithMagicCall::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ entities { edges { node { regularField magicField } } } }')->toArray();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame(
            [['node' => ['regularField' => 'regular', 'magicField' => 'magic']]],
            $result['data']['entities']['edges'],
        );
    }

    /** @return array<string, array{string}> */
    public static function requiredParameterProvider(): array
    {
        return [
            'exposed field' => ['GetterRequired'],
            'field which is not exposed' => ['GetterRequiredUnexposed'],
        ];
    }

    /**
     * Extracting by value calls the getter of every mapped field without
     * arguments, so one which requires a parameter is an error, exposed or
     * not, and with __call or not
     */
    #[DataProvider('requiredParameterProvider')]
    public function testGetterWithARequiredParameterIsAnError(string $group): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => $group]));

        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage(
            'Field title of entity ' . TestGetterRequiredParameter::class . ' is read by getTitle(), which '
            . 'extracting by value calls without arguments, but it requires a parameter.  Give its parameters '
            . 'default values, or extract the entity by reference with extractByValue: false.',
        );

        $driver->type(TestGetterRequiredParameter::class);
    }

    public function testGetterWithARequiredParameterIsExtractedByReference(): void
    {
        $driver = new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'GetterRequired', 'extractByValue' => false]),
        );

        $this->assertInstanceOf(ObjectType::class, $driver->type(TestGetterRequiredParameter::class));
    }

    public function testGetterWithAnOptionalParameterIsCalledWithoutIt(): void
    {
        $this->getEntityManager()->persist(new TestGetterOptionalParameter());
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'GetterOptional']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['entities' => $driver->completeConnection(TestGetterOptionalParameter::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ entities { edges { node { label } } } }')->toArray();

        $this->assertSame([['node' => ['label' => 'Label!']]], $result['data']['entities']['edges']);
    }
}
