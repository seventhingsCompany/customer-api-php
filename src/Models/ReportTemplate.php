<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class ReportTemplate
{
    public function __construct(
        public string $uuid,
        public string $name,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'] ?? '',
            name: $data['name'] ?? '',
        );
    }
}
