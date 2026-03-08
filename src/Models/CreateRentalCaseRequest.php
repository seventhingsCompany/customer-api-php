<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class CreateRentalCaseRequest
{
    /**
     * @param RentalCaseReferenceInput[]|null $references
     * @param string[]|null $attachments
     */
    public function __construct(
        public ?RentalCaseRenter $renter = null,
        public ?array $references = null,
        public ?string $pickupDate = null,
        public ?string $returnDate = null,
        public ?string $comment = null,
        public ?TimeInterval $recurringSchedule = null,
        public ?array $attachments = null,
    ) {}

    public function toArray(): array
    {
        $data = [];

        if ($this->renter !== null) {
            $data['renter'] = $this->renter->toArray();
        }

        if ($this->references !== null) {
            $data['references'] = array_map(fn(RentalCaseReferenceInput $r) => $r->toArray(), $this->references);
        }

        if ($this->pickupDate !== null) {
            $data['pickup_date'] = $this->pickupDate;
        }

        if ($this->returnDate !== null) {
            $data['return_date'] = $this->returnDate;
        }

        if ($this->comment !== null) {
            $data['comment'] = $this->comment;
        }

        if ($this->recurringSchedule !== null) {
            $data['recurring_schedule'] = $this->recurringSchedule->toArray();
        }

        if ($this->attachments !== null) {
            $data['attachments'] = $this->attachments;
        }

        return $data;
    }
}
