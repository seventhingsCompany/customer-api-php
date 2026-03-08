<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class UserResponse
{
    public function __construct(
        public string $uuid,
        public int $id,
        public string $email,
        public ?string $firstname,
        public ?string $lastname,
        public ?string $displayName,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            id: $data['id'],
            email: $data['email'],
            firstname: $data['firstname'] ?? null,
            lastname: $data['lastname'] ?? null,
            displayName: $data['display_name'] ?? null,
        );
    }
}
