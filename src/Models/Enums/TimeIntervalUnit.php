<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum TimeIntervalUnit: string
{
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case Years = 'years';
}
