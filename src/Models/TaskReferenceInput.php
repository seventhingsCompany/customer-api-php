<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\TaskReferenceType;

readonly class TaskReferenceInput
{
    public function __construct(
        public TaskReferenceType $type,
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
