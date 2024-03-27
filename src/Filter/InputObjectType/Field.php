<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ScalarType;

use function md5;
use function serialize;
use function uniqid;

/**
 * This class is used to create an InputObjectType of filters for a field
 * or association.  The generic term field is used for both here.
 */
class Field extends InputObjectType
{
    /** @param Filters[] $allowedFilters */
    public function __construct(
        TypeManager $typeManager,
        string $typeName,
        string $fieldName,
        ScalarType|ListOfType $type,
        array $allowedFilters,
    ) {
        /** @var array<string, array> $fields */
        $fields = [];

        foreach ($allowedFilters as $filter) {
            $fields[$filter->value] = [
                'name'        => $filter->value,
                'type'        => $filter->type($type),
                'description' => $filter->description(),
            ];

            // @codeCoverageIgnoreStart
            if (! $type instanceof ScalarType) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            if (! ($fields[$filter->value]['type'] instanceof Between)) {
                continue;
            }

            // Between is a special case filter.
            // To avoid creating a new Between type for each field,
            // check if the Between type exists and reuse it.
            if ($typeManager->has('Between_' . $type->name)) {
                $fields[$filter->value]['type'] = $typeManager->get('Between_' . $type->name);
            } else {
                $betweenType = new Between($type);
                $typeManager->set('Between_' . $type->name, $betweenType);
                $fields[$filter->value]['type'] = $betweenType;
            }
        }

        $typeName = $type instanceof ScalarType ? $type->name : uniqid();

        // ScalarType field filters are named by their field type
        // and a hash of the allowed filters
        parent::__construct([
            'name' => 'Filters_' . $typeName . '_' . md5(serialize($allowedFilters)),
            'description' => 'Field filters',
            'fields' => static fn () => $fields,
        ]);
    }
}
