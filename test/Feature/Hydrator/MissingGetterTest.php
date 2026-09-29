<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithMagicCall;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithoutGetter;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

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
}
