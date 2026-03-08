<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum UserSortBy: string
{
    case Id = 'id';
    case Email = 'email';
}
