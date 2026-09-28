<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Enum;

enum Priority: int
{
    case Low  = 1;
    case High = 5;
}
