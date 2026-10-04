<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ScalarType;

use function md5;
use function serialize;
use function substr;

/**
 * This class is used to create an InputObjectType of filters for a field
 * or association.  The generic term field is used for both here.
 */
class Field extends InputObjectType
{
    /** @param Filters[] $allowedFilters */
    public function __construct(
        readonly TypeContainer $typeContainer,
        readonly ScalarType $type,
        readonly array $allowedFilters,
    ) {
        $fields = self::filterFields($typeContainer, $type, $allowedFilters);

        parent::__construct([ // @phpstan-ignore argument.type
            'name' => self::nameFor($type, $allowedFilters),
            'description' => 'Field filters',
            'fields' => static fn () => $fields,
        ]);
    }

    /**
     * The fields of an input object of filters: one for each filter
     *
     * @param Filters[] $allowedFilters
     *
     * @return array<string, array<string, mixed>>
     */
    public static function filterFields(TypeContainer $typeContainer, ScalarType $type, array $allowedFilters): array
    {
        $fields = [];

        foreach ($allowedFilters as $filter) {
            $fields[$filter->value] = [
                'name'        => $filter->value,
                'type'        => $filter->type($type, $typeContainer),
                'description' => $filter->description(),
            ];
        }

        return $fields;
    }

    /**
     * Field filters are named by their field type and a short hash of the
     * allowed filters
     *
     * @param Filters[] $allowedFilters
     */
    public static function nameFor(ScalarType $type, array $allowedFilters): string
    {
        return 'Filters_' . $type->name() . '_' . substr(md5(serialize($allowedFilters)), 0, 8);
    }
}
