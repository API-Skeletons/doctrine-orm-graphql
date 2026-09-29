<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

/**
 * How the Json scalar exchanges a JSON value with a client
 */
enum JsonFormat: string
{
    /** As a string containing a JSON document, such as "{\"a\":1}" */
    case String = 'string';

    /** As the value itself, such as {"a": 1} */
    case Object = 'object';
}
