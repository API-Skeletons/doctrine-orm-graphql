<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Enum;

enum Size: string
{
    case Small = 'small';
    case Large = 'large';
}
