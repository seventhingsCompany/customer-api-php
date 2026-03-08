<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum SSOAppTarget: string
{
    case Web = 'web';
    case Mobile = 'mobile';
}
