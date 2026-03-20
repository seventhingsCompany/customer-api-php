<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\RenterType;

readonly class RentalCaseRenter
{
    public function __construct(
        public RenterType $type,
        public string $value,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: RenterType::from($data['type']),
            value: $data['value'],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'value' => $this->value,
        ];
    }
}
