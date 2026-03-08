<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\TaskReferenceStatus;
use Seventhings\Models\Enums\TaskReferenceType;

readonly class TaskReference
{
    public function __construct(
        public TaskReferenceType $type,
        public string $uuid,
        public string $name,
        public int $id,
        public TaskReferenceStatus $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: TaskReferenceType::from($data['type']),
            uuid: $data['uuid'],
            name: $data['name'],
            id: $data['id'],
            status: TaskReferenceStatus::from($data['status']),
        );
    }
}
