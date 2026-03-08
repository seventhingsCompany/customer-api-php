<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum LoginDeniedReason: string
{
    case LoginDeactivated = 'LoginDeactivated';
    case Banned = 'Banned';
    case EmailUnconfirmed = 'EmailUnconfirmed';
    case Inactive = 'Inactive';
    case OnlySSOLoginAllowed = 'OnlySSOLoginAllowed';
}
