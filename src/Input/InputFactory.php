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
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\Type;
use ReflectionClass;

use function array_filter;
use function array_flip;
use function array_intersect;
use function array_map;
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

        // A field may be named by its alias, as it is in the input, or by its
        // field name.  The order of the fields does not matter.
        $requiredFields = array_values(array_unique($this->toFieldNames($targetEntity, $requiredFields)));
        $optionalFields = array_values(array_unique($this->toFieldNames($targetEntity, $optionalFields)));

        $both = array_values(array_intersect($requiredFields, $optionalFields));
        if ($both) {
            throw new InputException(
                'Field ' . $both[0] . ' is in both the required and the optional fields of the input for entity '
                . $targetEntity->getEntityClass() . '.',
            );
        }

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
                    $self->addAllFields($targetEntity, $fields);
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
     */
    protected function addOptionalFields(
        Entity $targetEntity,
        array $optionalFields,
        array &$fields,
    ): void {
        // In the order of the entity's fields
        foreach ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->getFieldNames() as $fieldName) {
            if (! in_array($fieldName, $optionalFields, true)) {
                continue;
            }

            $this->addListedField($targetEntity, $fieldName, false, $fields);
        }
    }

    /**
     * @param string[]                            $requiredFields
     * @param array<int|string, InputObjectField> $fields
     */
    protected function addRequiredFields(
        Entity $targetEntity,
        array $requiredFields,
        array &$fields,
    ): void {
        // In the order of the entity's fields
        foreach ($this->entityManager->getClassMetadata($targetEntity->getEntityClass())->getFieldNames() as $fieldName) {
            if (! in_array($fieldName, $requiredFields, true)) {
                continue;
            }

            $this->addListedField($targetEntity, $fieldName, true, $fields);
        }
    }

    /**
     * With no field lists, every exposed field is input.  A field whose
     * column is nullable is optional; every other field is required.
     *
     * @param array<int|string, InputObjectField> $fields
     */
    protected function addAllFields(Entity $targetEntity, array &$fields): void
    {
        $classMetadata = $this->entityManager->getClassMetadata($targetEntity->getEntityClass());

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            /**
             * Do not include identifiers as input.  In the majority of cases there will be
             * no reason to set or update an identifier.  For the case where an identifier
             * should be set or updated, this factory is not the correct solution.
             */
            if ($classMetadata->isIdentifier($fieldName)) {
                continue;
            }

            // A column which is not exposed in this group is not part of the input
            if (! $this->isExposed($targetEntity, $fieldName)) {
                continue;
            }

            $this->addField($targetEntity, $fieldName, ! $classMetadata->isNullable($fieldName), $fields);
        }
    }

    /**
     * Add a field named in the required or optional list
     *
     * @param array<int|string, InputObjectField> $fields
     *
     * @throws InputException
     */
    private function addListedField(Entity $targetEntity, string $fieldName, bool $required, array &$fields): void
    {
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
            throw new InputException(
                'Identifier ' . $fieldName . ' is an invalid input. Identifiers should not be included in mutation input.',
            );
        }

        $this->addField($targetEntity, $fieldName, $required, $fields);
    }

    /**
     * Add an exposed field, named by its alias if it has one
     *
     * @param array<int|string, InputObjectField> $fields
     */
    private function addField(Entity $targetEntity, string $fieldName, bool $required, array &$fields): void
    {
        $fieldMetadata = $targetEntity->getEntityMetadata()->fields[$fieldName];
        $type          = $this->typeContainer->get($fieldMetadata->type);
        assert($type instanceof Type && $type instanceof NullableType && $type instanceof InputType);

        $name = $targetEntity->getExtractionMap()[$fieldName] ?? $fieldName;

        $fields[$name] = new InputObjectField([
            'name' => $name,
            'description' => (string) $fieldMetadata->description,
            'type' => $required ? Type::nonNull($type) : $type,
        ]);
    }

    /**
     * Name each field of a list by its field name rather than its alias
     *
     * @param string[] $names
     *
     * @return string[]
     */
    private function toFieldNames(Entity $targetEntity, array $names): array
    {
        $fieldNames = array_flip($targetEntity->getExtractionMap());

        return array_map(static fn (string $name): string => $fieldNames[$name] ?? $name, $names);
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

            // Suggest only fields which can be input, by their names in the input
            $exposedFieldNames = array_values(array_map(
                static fn (string $name): string => $targetEntity->getExtractionMap()[$name] ?? $name,
                array_filter(
                    $entityFieldNames,
                    fn (string $name): bool => $this->isExposed($targetEntity, $name),
                ),
            ));
            $suggestion        = $this->findSimilarString($fieldName, $exposedFieldNames);

            throw new InputException(
                'Field ' . $fieldName . ' is not a field of entity ' . $targetEntity->getEntityClass() . '.'
                . ($suggestion !== null ? ' Did you mean "' . $suggestion . '"?' : ''),
            );
        }
    }
}
