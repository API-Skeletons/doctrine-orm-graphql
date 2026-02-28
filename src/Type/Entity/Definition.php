<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ArrayObject;

/**
 * This class exists to wrap the entity definition information
 * before it is converted to a GraphQL type
 *
 * @extends ArrayObject<string, mixed>
 */
final class Definition extends ArrayObject
{
}
