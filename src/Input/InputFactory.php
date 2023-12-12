<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Input;

use ApiSkeletons\Doctrine\ORM\GraphQL\AbstractContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use Doctrine\ORM\EntityManager;
use Exception;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

use function count;
use function in_array;
use function uniqid;

/**
 * Create an input object type for a mutation
 */
class InputFactory extends AbstractContainer
{
    public function __construct(
        protected Config $config,
        protected EntityManager $entityManager,
        protected TypeManager $typeManager,
    ) {
    }

    /**
     * @param string[] $requiredFields An optional list of just the required fields you want for the mutation.
     *                                 This allows specific fields per mutation.
     * @param string[] $optionalFields An optional list of optional fields you want for the mutation.
     *                                 This allows specific fields per mutation.
     *
     * @throws Error
     */
    public function get(string $id, array $requiredFields = [], array $optionalFields = []): InputObjectType
    {
        $fields       = [];
        $targetEntity = $this->typeManager->build(Entity::class, $id);

        if (! count($requiredFields) && ! count($optionalFields)) {
            $this->addAllFieldsAsRequired($targetEntity, $fields);
        } else {
            $this->addRequiredFields($targetEntity, $requiredFields, $fields);
            $this->addOptionalFields($targetEntity, $optionalFields, $fields);
        }

        return new InputObjectType([
            'name' => $targetEntity->getTypeName() . '_Input_' . uniqid(),
            'description' => $targetEntity->getDescription(),
            'fields' => static fn () => $fields,
        ]);
    }

    /**
     * @param string[]                     $optionalFields
     * @param array<int, InputObjectField> $fields
     */
    protected function addOptionalFields(
        mixed $targetEntity,
        array $optionalFields,
        array &$fields,
    ): void {
        foreach ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->getFieldNames() as $fieldName) {
            if (! in_array($fieldName, $optionalFields)) {
                continue;
            }

            /**
             * Do not include identifiers as input.  In the majority of cases there will be
             * no reason to set or update an identifier.  For the case where an identifier
             * should be set or updated, this factory is not the correct solution.
             */
            if ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->isIdentifier($fieldName)) {
                throw new Exception('Identifier ' . $fieldName . ' is an invalid input.');
            }

            $fields[$fieldName] = new InputObjectField([
                'name' => $fieldName,
                'description' => (string) $targetEntity->getMetadata()['fields'][$fieldName]['description'],
                'type' => $this->typeManager->get($targetEntity->getMetadata()['fields'][$fieldName]['type']),
            ]);
        }
    }

    /**
     * @param string[]                     $requiredFields
     * @param array<int, InputObjectField> $fields
     */
    protected function addRequiredFields(
        mixed $targetEntity,
        array $requiredFields,
        array &$fields,
    ): void {
        foreach ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->getFieldNames() as $fieldName) {
            if (! in_array($fieldName, $requiredFields)) {
                continue;
            }

            /**
             * Do not include identifiers as input.  In the majority of cases there will be
             * no reason to set or update an identifier.  For the case where an identifier
             * should be set or updated, this factory is not the correct solution.
             */
            if ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->isIdentifier($fieldName)) {
                throw new Exception('Identifier ' . $fieldName . ' is an invalid input.');
            }

            $fields[$fieldName] = new InputObjectField([
                'name' => $fieldName,
                'description' => (string) $targetEntity->getMetadata()['fields'][$fieldName]['description'],
                'type' => Type::nonNull($this->typeManager->get(
                    $targetEntity->getMetadata()['fields'][$fieldName]['type'],
                )),
            ]);
        }
    }

    /** @param array<int, InputObjectField> $fields */
    protected function addAllFieldsAsRequired(mixed $targetEntity, array &$fields): void
    {
        foreach ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->getFieldNames() as $fieldName) {
            /**
             * Do not include identifiers as input.  In the majority of cases there will be
             * no reason to set or update an identifier.  For the case where an identifier
             * should be set or updated, this factory is not the correct solution.
             */
            if ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->isIdentifier($fieldName)) {
                continue;
            }

            $fields[$fieldName] = new InputObjectField([
                'name' => $fieldName,
                'description' => (string) $targetEntity->getMetadata()['fields'][$fieldName]['description'],
                'type' => Type::nonNull($this->typeManager->get($targetEntity->getMetadata()['fields'][$fieldName]['type'])),
            ]);
        }
    }
}
