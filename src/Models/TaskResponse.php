<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\TaskStatus;

readonly class TaskResponse
{
    /**
     * @param string[] $assignees
     * @param TaskReference[] $references
     * @param TimeInterval[] $reminders
     * @param AttachmentFile[] $attachments
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public TaskStatus $status,
        public ?string $deadline,
        public array $assignees,
        public string $author,
        public array $references,
        public array $reminders,
        public ?TimeInterval $recurringSchedule,
        public ?string $comment,
        public array $attachments,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            title: $data['title'],
            status: TaskStatus::from($data['status']),
            deadline: $data['deadline'] ?? null,
            assignees: $data['assignees'] ?? [],
            author: $data['author'],
            references: array_map(fn(array $r) => TaskReference::fromArray($r), $data['references'] ?? []),
            reminders: array_map(fn(array $r) => TimeInterval::fromArray($r), $data['reminders'] ?? []),
            recurringSchedule: isset($data['recurring_schedule']) ? TimeInterval::fromArray($data['recurring_schedule']) : null,
            comment: $data['comment'] ?? null,
            attachments: array_map(fn(array $a) => AttachmentFile::fromArray($a), $data['attachments'] ?? []),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }
}
