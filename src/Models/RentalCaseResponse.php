<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\RentalCaseStatus;

readonly class RentalCaseResponse
{
    /**
     * @param RentalCaseReference[] $references
     * @param AttachmentFile[] $attachments
     */
    public function __construct(
        public string $uuid,
        public RentalCaseStatus $status,
        public string $title,
        public ?RentalCaseRenter $renter,
        public array $references,
        public ?string $issueDate,
        public ?string $dueDate,
        public ?string $comment,
        public ?TimeInterval $issueDateReminder,
        public ?TimeInterval $dueDateReminder,
        public ?string $responsibleUserUuid,
        public ?string $author,
        public array $attachments,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            status: RentalCaseStatus::from($data['status']),
            title: $data['title'],
            renter: isset($data['renter']) ? RentalCaseRenter::fromArray($data['renter']) : null,
            references: array_map(fn(array $r) => RentalCaseReference::fromArray($r), $data['references'] ?? []),
            issueDate: $data['issue_date'] ?? null,
            dueDate: $data['due_date'] ?? null,
            comment: $data['comment'] ?? null,
            issueDateReminder: isset($data['issue_date_reminder']) ? TimeInterval::fromArray($data['issue_date_reminder']) : null,
            dueDateReminder: isset($data['due_date_reminder']) ? TimeInterval::fromArray($data['due_date_reminder']) : null,
            responsibleUserUuid: $data['responsible_user_uuid'] ?? null,
            author: $data['author'] ?? null,
            attachments: array_map(fn(array $a) => AttachmentFile::fromArray($a), $data['attachments'] ?? []),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }
}
