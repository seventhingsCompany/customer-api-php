<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\RentalCaseReferenceType;

readonly class RentalCaseReferenceInput
{
    public function __construct(
        public RentalCaseReferenceType $type,
        public string $uuid,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'uuid' => $this->uuid,
        ];
    }
}
