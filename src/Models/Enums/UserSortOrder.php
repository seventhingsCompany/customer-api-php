<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum UserSortOrder: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
