<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class FieldAttribute
{
    public function __construct(
        public string $type,
        public mixed $value,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            value: $data['value'],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'value' => $this->value,
        ];
    }
}
