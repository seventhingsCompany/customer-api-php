<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum RentalCaseStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Borrowed = 'borrowed';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case ReturnOverdue = 'return_overdue';
    case PickupOverdue = 'pickup_overdue';
}
