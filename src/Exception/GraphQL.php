<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Exception;

use GraphQL\Error\Error;

/**
 * Base exception for all Doctrine ORM GraphQL exceptions
 *
 * Extends GraphQL\Error\Error to ensure compatibility with webonyx/graphql-php
 * error handling and reporting.
 */
class GraphQL extends Error
{
}
