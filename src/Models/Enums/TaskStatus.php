<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
