<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\TimeIntervalUnit;

readonly class TimeInterval
{
    public function __construct(
        public TimeIntervalUnit $unit,
        public int $value,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            unit: TimeIntervalUnit::from($data['unit']),
            value: $data['value'],
        );
    }

    public function toArray(): array
    {
        return [
            'unit' => $this->unit->value,
            'value' => $this->value,
        ];
    }
}
