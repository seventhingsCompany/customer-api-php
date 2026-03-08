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
        public ?string $renter,
        public array $references,
        public ?string $pickupDate,
        public ?string $returnDate,
        public ?string $comment,
        public ?TimeInterval $recurringSchedule,
        public array $attachments,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            status: RentalCaseStatus::from($data['status']),
            renter: $data['renter'] ?? null,
            references: array_map(fn(array $r) => RentalCaseReference::fromArray($r), $data['references'] ?? []),
            pickupDate: $data['pickup_date'] ?? null,
            returnDate: $data['return_date'] ?? null,
            comment: $data['comment'] ?? null,
            recurringSchedule: isset($data['recurring_schedule']) ? TimeInterval::fromArray($data['recurring_schedule']) : null,
            attachments: array_map(fn(array $a) => AttachmentFile::fromArray($a), $data['attachments'] ?? []),
            createdAt: $data['created_at'],
            updatedAt: $data['updated_at'],
        );
    }
}
