<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Criteria\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Criteria\Filters as FiltersDef;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

class FiltersInputObjectType extends InputObjectType
{
    /** @param string[] $allowedFilters */
    public function __construct(string $typeName, string $fieldName, ScalarType|ListOfType $type, array $allowedFilters)
    {
        $fields       = [];
        $descriptions = FiltersDef::getDescriptions();

        foreach ($allowedFilters as $filter) {
            $filterType = $type;

            switch ($filter) {
                case FiltersDef::SORT:
                    $filterType = Type::string();
                    break;
                case FiltersDef::ISNULL:
                    $filterType = Type::boolean();
                    break;
                case FiltersDef::BETWEEN:
                    $filterType = new BetweenInputObjectType(
                        $typeName,
                        $fieldName,
                        $type,
                    );
                    break;
                case FiltersDef::IN:
                case FiltersDef::NOTIN:
                    $filterType = Type::listOf($type);
                    break;
                case FiltersDef::STARTSWITH:
                case FiltersDef::ENDSWITH:
                case FiltersDef::CONTAINS:
                    if ($type !== Type::string() && $type !== Type::id()) {
                        continue 2;
                    }

                    break;
            }

            $fields[$filter] = [
                'name'        => $filter,
                'type'        => $filterType,
                'description' => $descriptions[$filter],
            ];
        }

        parent::__construct([
            'name' => $typeName . '_' . $fieldName . '_filters',
            'description' => 'Field filters',
            'fields' => static fn () => $fields,
        ]);
    }
}
