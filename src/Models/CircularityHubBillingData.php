<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class CircularityHubBillingData
{
    public function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public ?string $street,
        public ?string $houseNumber,
        public ?string $zipCode,
        public ?string $city,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            street: $data['street'] ?? null,
            houseNumber: $data['house_number'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            city: $data['city'] ?? null,
        );
    }
}
