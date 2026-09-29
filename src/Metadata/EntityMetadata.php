<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;

use function class_exists;

/**
 * The metadata of an entity exposed with #[Entity]
 *
 * This is built from, and exports to, the array form of the metadata, which
 * is what is cached.  Fields and associations share the fields key in the
 * array; a field has a type and an association does not.  Keys this class
 * does not use, such as keys added by a metadata.build listener, are ignored
 * here and remain in the array.
 */
final readonly class EntityMetadata
{
    /**
     * @param class-string                         $entityClass
     * @param list<string>                         $excludeFilters
     * @param array<string, FieldMetadata>         $fields
     * @param array<string, AssociationMetadata>   $associations
     * @param array<string, ComputedFieldMetadata> $computedFields
     */
    public function __construct(
        public string $entityClass,
        public bool $extractByValue,
        public int $limit,
        public array $excludeFilters,
        public string|null $description,
        public string $typeName,
        public array $fields,
        public array $associations,
        public array $computedFields,
    ) {
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @throws MetadataException
     */
    public static function fromArray(array $array): self
    {
        $reader      = new ArrayReader($array, 'an entity');
        $entityClass = $reader->string('entityClass');

        if (! class_exists($entityClass)) {
            throw new MetadataException('Metadata names entity ' . $entityClass . ' but the class does not exist.');
        }

        $reader = new ArrayReader($array, 'entity ' . $entityClass);

        $fields       = [];
        $associations = [];
        $fieldContext = 'entity ' . $entityClass . ' field ';
        foreach ($reader->arrayOfArrays('fields', $fieldContext) as $name => $fieldArray) {
            $context = $fieldContext . $name;

            if ((new ArrayReader($fieldArray, $context))->has('type')) {
                $fields[$name] = FieldMetadata::fromArray($name, $fieldArray, $context);
            } else {
                $associations[$name] = AssociationMetadata::fromArray($name, $fieldArray, $context);
            }
        }

        $computedFields = [];
        if ($reader->has('computedFields')) {
            $computedFieldContext = 'entity ' . $entityClass . ' computed field ';
            foreach ($reader->arrayOfArrays('computedFields', $computedFieldContext) as $name => $computedFieldArray) {
                $computedFields[$name] = ComputedFieldMetadata::fromArray(
                    $computedFieldArray,
                    $computedFieldContext . $name,
                );
            }
        }

        return new self(
            $entityClass,
            $reader->bool('extractByValue'),
            $reader->int('limit'),
            $reader->stringList('excludeFilters'),
            $reader->nullableString('description'),
            $reader->string('typeName'),
            $fields,
            $associations,
            $computedFields,
        );
    }

    /**
     * Export in the shape the MetadataFactory builds
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $fields = [];
        foreach ($this->fields as $name => $field) {
            $fields[$name] = $field->toArray();
        }

        foreach ($this->associations as $name => $association) {
            $fields[$name] = $association->toArray();
        }

        $array = [
            'entityClass' => $this->entityClass,
            'extractByValue' => $this->extractByValue,
            'limit' => $this->limit,
            'fields' => $fields,
            'excludeFilters' => $this->excludeFilters,
            'description' => $this->description,
            'typeName' => $this->typeName,
        ];

        if ($this->computedFields) {
            $computedFields = [];
            foreach ($this->computedFields as $name => $computedField) {
                $computedFields[$name] = $computedField->toArray();
            }

            $array['computedFields'] = $computedFields;
        }

        return $array;
    }
}
