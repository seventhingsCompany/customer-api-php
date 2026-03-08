<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class UpdateTaskRequest
{
    /**
     * @param string[] $assignees
     * @param TaskReferenceInput[] $references
     * @param TimeInterval[] $reminders
     * @param string[]|null $attachments
     */
    public function __construct(
        public string $title,
        public ?string $deadline,
        public array $assignees,
        public array $references,
        public array $reminders,
        public ?TimeInterval $recurringSchedule,
        public ?string $comment = null,
        public ?array $attachments = null,
        public ?bool $notify = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'title' => $this->title,
            'deadline' => $this->deadline,
            'assignees' => $this->assignees,
            'references' => array_map(fn(TaskReferenceInput $r) => $r->toArray(), $this->references),
            'reminders' => array_map(fn(TimeInterval $r) => $r->toArray(), $this->reminders),
            'recurring_schedule' => $this->recurringSchedule?->toArray(),
        ];

        if ($this->comment !== null) {
            $data['comment'] = $this->comment;
        }

        if ($this->attachments !== null) {
            $data['attachments'] = $this->attachments;
        }

        if ($this->notify !== null) {
            $data['notify'] = $this->notify;
        }

        return $data;
    }
}
