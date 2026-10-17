<?php

declare(strict_types=1);

namespace Seventhings\Models;

/**
 * Request body for creating a rental case. Leave $isFullDay null to let the
 * API infer the kind from $issueAt and $dueAt (ISO 8601 date-times for timed
 * rentals).
 */
readonly class CreateRentalCaseRequest
{
    /**
     * @param RentalCaseReferenceInput[] $references
     * @param string[] $attachments
     */
    public function __construct(
        public string $title,
        public string $issueDate,
        public string $dueDate,
        public string $comment,
        public string $responsibleUserUuid,
        public RentalCaseRenter $renter,
        public array $references,
        public array $attachments,
        public ?TimeInterval $issueDateReminder = null,
        public ?TimeInterval $dueDateReminder = null,
        public ?bool $isFullDay = null,
        public ?string $issueAt = null,
        public ?string $dueAt = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'title' => $this->title,
            'issue_date' => $this->issueDate,
            'due_date' => $this->dueDate,
            'comment' => $this->comment,
            'responsible_user_uuid' => $this->responsibleUserUuid,
            'renter' => $this->renter->toArray(),
            'references' => array_map(fn(RentalCaseReferenceInput $r) => $r->toArray(), $this->references),
            'attachments' => $this->attachments,
        ];

        if ($this->issueDateReminder !== null) {
            $data['issue_date_reminder'] = $this->issueDateReminder->toArray();
        }

        if ($this->dueDateReminder !== null) {
            $data['due_date_reminder'] = $this->dueDateReminder->toArray();
        }

        if ($this->isFullDay !== null) {
            $data['is_full_day'] = $this->isFullDay;
        }

        if ($this->issueAt !== null) {
            $data['issue_at'] = $this->issueAt;
        }

        if ($this->dueAt !== null) {
            $data['due_at'] = $this->dueAt;
        }

        return $data;
    }
}
