<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class TaskHistoryEntry
{
    /** @param string $details JSON-encoded snapshot, or an empty string. */
    public function __construct(
        public string $taskUuid,
        public string $userUuid,
        public string $occurredAt,
        public string $eventName,
        public string $description,
        public string $details,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            taskUuid: $data['task_uuid'] ?? '',
            userUuid: $data['user_uuid'] ?? '',
            occurredAt: $data['occurred_at'] ?? '',
            eventName: $data['event_name'] ?? '',
            description: $data['description'] ?? '',
            details: $data['details'] ?? '',
        );
    }
}
