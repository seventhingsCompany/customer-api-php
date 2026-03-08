<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\TaskReferenceType;
use Seventhings\Models\Enums\TaskStatus;

readonly class TaskListOptions
{
    public function __construct(
        public ?TaskStatus $status = null,
        public ?string $deadlineFrom = null,
        public ?string $deadlineTo = null,
        public ?string $assignee = null,
        public ?string $author = null,
        public ?TaskReferenceType $referenceType = null,
    ) {}

    public function toQueryString(): string
    {
        $parts = [];

        if ($this->status !== null) {
            $parts[] = 'status=' . rawurlencode($this->status->value);
        }

        if ($this->deadlineFrom !== null) {
            $parts[] = 'deadline_from=' . rawurlencode($this->deadlineFrom);
        }

        if ($this->deadlineTo !== null) {
            $parts[] = 'deadline_to=' . rawurlencode($this->deadlineTo);
        }

        if ($this->assignee !== null) {
            $parts[] = 'assignee=' . rawurlencode($this->assignee);
        }

        if ($this->author !== null) {
            $parts[] = 'author=' . rawurlencode($this->author);
        }

        if ($this->referenceType !== null) {
            $parts[] = 'reference_type=' . rawurlencode($this->referenceType->value);
        }

        return implode('&', $parts);
    }
}
