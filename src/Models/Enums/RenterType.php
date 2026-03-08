<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum RenterType: string
{
    case Plain = 'plain';
    case User = 'user';
}
