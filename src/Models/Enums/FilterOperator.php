<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum FilterOperator: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case Gt = 'gt';
    case GtOrNull = 'gt_or_null';
    case Gte = 'gte';
    case GteOrNull = 'gte_or_null';
    case Lt = 'lt';
    case LtOrNull = 'lt_or_null';
    case Lte = 'lte';
    case LteOrNull = 'lte_or_null';
    case Like = 'like';
    case NotLike = 'not_like';
    case In = 'in';
    case Nin = 'nin';
}
