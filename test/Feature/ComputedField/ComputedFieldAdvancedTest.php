<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function str_contains;
use function strpos;
use function substr;

class ComputedFieldAdvancedTest extends TestCase
{
    public function testComputedFieldWithCustomName(): void
    {
        // Test explicit name override
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'computedFieldNameTest']));

        $graphQLType = $driver->type(User::class);
        $fields      = $graphQLType->getFields();

        // Verify custom name is used instead of derived name
        $this->assertArrayHasKey('displayName', $fields);
        $this->assertArrayNotHasKey('fullDisplayName', $fields);
        $this->assertEquals('Display name for UI', $fields['displayName']->description);
    }

    public function testMultipleComputedFieldsSameEntity(): void
    {
        // Test multiple computed fields on same entity
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'multiComputedTest']));

        $graphQLType = $driver->type(User::class);
        $fields      = $graphQLType->getFields();

        $this->assertArrayHasKey('fullName', $fields);
        $this->assertArrayHasKey('emailDomain', $fields);
        $this->assertArrayHasKey('isActive', $fields);
    }

    public function testComputedFieldResolutionMultipleFields(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'multiComputedTest']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'users' => $driver->completeConnection(User::class),
                ],
            ]),
        ]);

        $query = '
            {
                users {
                    edges {
                        node {
                            name
                            email
                            fullName
                            emailDomain
                            isActive
                        }
                    }
                }
            }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->assertEmpty($output['errors'] ?? []);

        if (empty($output['data']['users']['edges'])) {
            return;
        }

        $user = $output['data']['users']['edges'][0]['node'];

        // Test fullName (name + email)
        $this->assertArrayHasKey('fullName', $user);
        $this->assertStringContainsString($user['name'], $user['fullName']);
        $this->assertStringContainsString($user['email'], $user['fullName']);

        // Test emailDomain
        $this->assertArrayHasKey('emailDomain', $user);
        if (str_contains($user['email'], '@')) {
            $expectedDomain = substr($user['email'], strpos($user['email'], '@') + 1);
            $this->assertEquals($expectedDomain, $user['emailDomain']);
        }

        // Test isActive
        $this->assertArrayHasKey('isActive', $user);
        $this->assertIsBool($user['isActive']);
    }

    public function testComputedFieldDerivedNameFromIsMethod(): void
    {
        // Test that isXxx() methods keep their name
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'isMethodTest']));

        $graphQLType = $driver->type(User::class);
        $fields      = $graphQLType->getFields();

        $this->assertArrayHasKey('isActive', $fields);
        $this->assertEquals('Boolean', (string) $fields['isActive']->getType());
    }
}
