<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Security;

use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * A Doctrine SQL filter which allows only Phish of the artists and only the
 * performances in Utah
 */
final class UtahPhishFilter extends SQLFilter
{
    /**
     * The alias is not typed, as it is not in ORM 2
     *
     * @param ClassMetadata<object> $targetEntity
     * @param string                $targetTableAlias
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        return match ($targetEntity->getName()) {
            Artist::class => $targetTableAlias . ".name = 'Phish'",
            Performance::class => $targetTableAlias . ".state = 'Utah'",
            default => '',
        };
    }
}
