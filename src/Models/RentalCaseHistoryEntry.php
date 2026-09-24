<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class RentalCaseHistoryEntry
{
    /** @param string $details JSON-encoded snapshot, or an empty string. */
    public function __construct(
        public string $rentalCaseUuid,
        public string $userUuid,
        public string $occurredAt,
        public string $eventName,
        public string $description,
        public string $details,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            rentalCaseUuid: $data['rental_case_uuid'] ?? '',
            userUuid: $data['user_uuid'] ?? '',
            occurredAt: $data['occurred_at'] ?? '',
            eventName: $data['event_name'] ?? '',
            description: $data['description'] ?? '',
            details: $data['details'] ?? '',
        );
    }
}
