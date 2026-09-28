<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Input;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input as InputException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\SuggestSimilarString;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use Doctrine\ORM\EntityManager;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use ReflectionClass;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function in_array;
use function md5;
use function preg_match;
use function serialize;
use function sort;
use function substr;

/**
 * Create an input object type for a mutation
 */
final class InputFactory
{
    use SuggestSimilarString;

    /**
     * Input types built so far, keyed by type name, with the entity and
     * fields they were built for
     *
     * @var array<string, array{signature: string, type: InputObjectType}>
     */
    private array $inputTypes = [];

    public function __construct(
        protected readonly Config $config,
        protected readonly EntityManager $entityManager,
        protected readonly EntityTypeContainer $entityTypeContainer,
        protected readonly TypeContainer $typeContainer,
    ) {
    }

    /**
     * @param string[]    $requiredFields An optional list of just the required fields you want for the mutation.
     *                                    This allows specific fields per mutation.
     * @param string[]    $optionalFields An optional list of optional fields you want for the mutation.
     *                                    This allows specific fields per mutation.
     * @param string|null $name           An optional name for the input type.  When it is not given the
     *                                    name is derived from the entity and the fields.
     *
     * @throws Error
     */
    public function get(
        string $id,
        array $requiredFields = [],
        array $optionalFields = [],
        string|null $name = null,
    ): InputObjectType {
        $targetEntity = $this->entityTypeContainer->get($id);
        assert($targetEntity instanceof Entity);

        // The order of the fields does not matter
        $requiredFields = array_values(array_unique($requiredFields));
        $optionalFields = array_values(array_unique($optionalFields));
        sort($requiredFields);
        sort($optionalFields);

        $signature = serialize([$targetEntity->getEntityClass(), $requiredFields, $optionalFields]);
        $name    ??= $this->getDefaultName($targetEntity, $requiredFields, $optionalFields);

        if (isset($this->inputTypes[$name])) {
            if ($this->inputTypes[$name]['signature'] !== $signature) {
                throw new InputException(
                    'Input type name ' . $name . ' is already used for different fields.',
                );
            }

            return $this->inputTypes[$name]['type'];
        }

        if (! preg_match('/^[_a-zA-Z][_a-zA-Z0-9]*$/', $name)) {
            throw new InputException('Input type name ' . $name . ' is not a valid GraphQL name.');
        }

        $type = $this->build($targetEntity, $requiredFields, $optionalFields, $name);

        $this->inputTypes[$name] = ['signature' => $signature, 'type' => $type];

        return $type;
    }

    /**
     * An input with no field lists is named <type>_Input.  Otherwise a hash of
     * the fields is appended, so each set of fields has its own stable name.
     *
     * @param string[] $requiredFields
     * @param string[] $optionalFields
     */
    private function getDefaultName(Entity $targetEntity, array $requiredFields, array $optionalFields): string
    {
        $name = $targetEntity->getTypeName() . '_Input';

        if (! count($requiredFields) && ! count($optionalFields)) {
            return $name;
        }

        return $name . '_' . substr(md5(serialize([$requiredFields, $optionalFields])), 0, 8);
    }

    /**
     * @param string[] $requiredFields
     * @param string[] $optionalFields
     */
    private function build(
        Entity $targetEntity,
        array $requiredFields,
        array $optionalFields,
        string $name,
    ): InputObjectType {
        $self = $this;

        return (new ReflectionClass(InputObjectType::class))
            ->newLazyGhost(static function (InputObjectType $object) use ($self, $targetEntity, $requiredFields, $optionalFields, $name): void {
                $fields = [];

                $self->assertFieldsExist($targetEntity, array_merge($requiredFields, $optionalFields));

                if (! count($requiredFields) && ! count($optionalFields)) {
                    $self->addAllFieldsAsRequired($targetEntity, $fields);
                } else {
                    $self->addRequiredFields($targetEntity, $requiredFields, $fields);
                    $self->addOptionalFields($targetEntity, $optionalFields, $fields);
                }

                /** @psalm-suppress DirectConstructorCall */
                $object->__construct([
                    'name' => $name,
                    'description' => $targetEntity->getDescription(),
                    'fields' => static fn () => $fields,
                ]);
            });
    }

    /**
     * @param string[]                            $optionalFields
     * @param array<int|string, InputObjectField> $fields
     *
     * @psalm-suppress MixedArgumentTypeCoercion
     */
    protected function addOptionalFields(
        Entity $targetEntity,
        array $optionalFields,
        array &$fields,
    ): void {
        foreach ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->getFieldNames() as $fieldName) {
            if (! in_array($fieldName, $optionalFields)) {
                continue;
            }

            if (! $this->isExposed($targetEntity, $fieldName)) {
                throw new InputException(
                    'Field ' . $fieldName . ' is not exposed for entity ' . $targetEntity->getEntityClass()
                    . ' in group ' . $this->config->getGroup() . ' and cannot be used as input.',
                );
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
                'description' => (string) $targetEntity->getEntityMetadata()->fields[$fieldName]->description,
                'type' => $this->typeContainer->get($targetEntity->getEntityMetadata()->fields[$fieldName]->type),
            ]);
        }
    }

    /**
     * @param string[]                            $requiredFields
     * @param array<int|string, InputObjectField> $fields
     *
     * @psalm-suppress MixedArgument
     */
    protected function addRequiredFields(
        Entity $targetEntity,
        array $requiredFields,
        array &$fields,
    ): void {
        foreach ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->getFieldNames() as $fieldName) {
            if (! in_array($fieldName, $requiredFields)) {
                continue;
            }

            if (! $this->isExposed($targetEntity, $fieldName)) {
                throw new InputException(
                    'Field ' . $fieldName . ' is not exposed for entity ' . $targetEntity->getEntityClass()
                    . ' in group ' . $this->config->getGroup() . ' and cannot be used as input.',
                );
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
                'description' => (string) $targetEntity->getEntityMetadata()->fields[$fieldName]->description,
                'type' => Type::nonNull($this->typeContainer->get(
                    $targetEntity->getEntityMetadata()->fields[$fieldName]->type,
                )),
            ]);
        }
    }

    /**
     * @param array<int|string, InputObjectField> $fields
     *
     * @psalm-suppress MixedArrayAccess, MixedArgument, MixedArgumentTypeCoercion
     */
    protected function addAllFieldsAsRequired(Entity $targetEntity, array &$fields): void
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

            // A column which is not exposed in this group is not part of the input
            if (! $this->isExposed($targetEntity, $fieldName)) {
                continue;
            }

            $alias = $targetEntity->getExtractionMap()[$fieldName] ?? null;

            $fields[$alias ?? $fieldName] = new InputObjectField([
                'name' => $alias ?? $fieldName,
                'description' => (string) $targetEntity->getEntityMetadata()->fields[$fieldName]->description,
                'type' => Type::nonNull($this->typeContainer->get($targetEntity->getEntityMetadata()->fields[$fieldName]->type)),
            ]);
        }
    }

    /**
     * Whether a field is exposed by a #[Field] attribute in the configured group
     */
    private function isExposed(Entity $targetEntity, string $fieldName): bool
    {
        return isset($targetEntity->getEntityMetadata()->fields[$fieldName]);
    }

    /**
     * Every name in the required and optional lists must be a field of the
     * entity.  A typo would otherwise be silently ignored.
     *
     * @param string[] $fieldNames
     *
     * @throws InputException
     */
    private function assertFieldsExist(Entity $targetEntity, array $fieldNames): void
    {
        $entityFieldNames = $this->entityManager
            ->getClassMetadata($targetEntity->getEntityClass())
            ->getFieldNames();

        foreach ($fieldNames as $fieldName) {
            if (in_array($fieldName, $entityFieldNames, true)) {
                continue;
            }

            // Suggest only fields which can be input
            $exposedFieldNames = array_values(array_filter(
                $entityFieldNames,
                fn (string $name): bool => $this->isExposed($targetEntity, $name),
            ));
            $suggestion        = $this->findSimilarString($fieldName, $exposedFieldNames);

            throw new InputException(
                'Field ' . $fieldName . ' is not a field of entity ' . $targetEntity->getEntityClass() . '.'
                . ($suggestion !== null ? ' Did you mean "' . $suggestion . '"?' : ''),
            );
        }
    }
}
