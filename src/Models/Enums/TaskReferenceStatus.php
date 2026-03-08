<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum TaskReferenceStatus: string
{
    case Open = 'open';
    case Done = 'done';
}
