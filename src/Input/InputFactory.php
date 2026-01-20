<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Input;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input as InputException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use Doctrine\ORM\EntityManager;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use ReflectionClass;

use function count;
use function in_array;
use function uniqid;

/**
 * Create an input object type for a mutation
 */
class InputFactory
{
    public function __construct(
        protected readonly Config $config,
        protected readonly EntityManager $entityManager,
        protected readonly EntityTypeContainer $entityTypeContainer,
        protected readonly TypeContainer $typeContainer,
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
        $self = $this;

        return (new ReflectionClass(InputObjectType::class))
            ->newLazyGhost(static function (InputObjectType $object) use ($self, $id, $requiredFields, $optionalFields): void {
                $fields       = [];
                $targetEntity = $self->entityTypeContainer->get($id);

                if (! count($requiredFields) && ! count($optionalFields)) {
                    $self->addAllFieldsAsRequired($targetEntity, $fields);
                } else {
                    $self->addRequiredFields($targetEntity, $requiredFields, $fields);
                    $self->addOptionalFields($targetEntity, $optionalFields, $fields);
                }

                $object->__construct([
                    'name' => $targetEntity->getTypeName() . '_Input_' . uniqid(),
                    'description' => $targetEntity->getDescription(),
                    'fields' => static fn () => $fields,
                ]);
            });
    }

    /**
     * @param string[]                            $optionalFields
     * @param array<int|string, InputObjectField> $fields
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
             *
             * @phpcs-disable
             */
            if ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->isIdentifier($fieldName)) {
                throw new InputException('Identifier ' . $fieldName . ' is an invalid input. Identifiers should not be included in mutation input.');
            }

            $alias = $targetEntity->getExtractionMap()[$fieldName] ?? null;

            $fields[$alias ?? $fieldName] = new InputObjectField([
                'name' => $alias ?? $fieldName,
                'description' => (string) $targetEntity->getMetadata()['fields'][$fieldName]['description'],
                'type' => $this->typeContainer->get($targetEntity->getMetadata()['fields'][$fieldName]['type']),
            ]);
        }
    }

    /**
     * @param string[]                            $requiredFields
     * @param array<int|string, InputObjectField> $fields
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
                throw new InputException('Identifier ' . $fieldName . ' is an invalid input. Identifiers should not be included in mutation input.');
            }

            $alias = $targetEntity->getExtractionMap()[$fieldName] ?? null;

            $fields[$alias ?? $fieldName] = new InputObjectField([
                'name' => $alias ?? $fieldName,
                'description' => (string) $targetEntity->getMetadata()['fields'][$fieldName]['description'],
                'type' => Type::nonNull($this->typeContainer->get(
                    $targetEntity->getMetadata()['fields'][$fieldName]['type'],
                )),
            ]);
        }
    }

    /** @param array<int|string, InputObjectField> $fields */
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

            $alias = $targetEntity->getExtractionMap()[$fieldName] ?? null;

            $fields[$alias ?? $fieldName] = new InputObjectField([
                'name' => $alias ?? $fieldName,
                'description' => (string) $targetEntity->getMetadata()['fields'][$fieldName]['description'],
                'type' => Type::nonNull($this->typeContainer->get($targetEntity->getMetadata()['fields'][$fieldName]['type'])),
            ]);
        }
    }
}
