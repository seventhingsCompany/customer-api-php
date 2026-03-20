<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\RentalCaseReferenceType;

readonly class RentalCaseReference
{
    public function __construct(
        public RentalCaseReferenceType $type,
        public string $uuid,
        public ?string $name,
        public ?int $id,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: RentalCaseReferenceType::from($data['type']),
            uuid: $data['uuid'],
            name: $data['name'] ?? null,
            id: $data['id'] ?? null,
        );
    }
}
