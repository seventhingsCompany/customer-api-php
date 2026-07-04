<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\SortDirection;

/**
 * PersonListOptions configures pagination and sorting for person list
 * requests.
 *
 * Unlike users, the persons endpoint sorts with the deep-object
 * `sort[field]=DIR` format (not `sort_by`/`order`), where each key is a
 * free-form field key (see field definitions) and the direction is ASC/DESC.
 */
readonly class PersonListOptions
{
    /**
     * @param array<string, SortDirection> $sort field key => direction
     */
    public function __construct(
        public ?int $page = null,
        public ?int $perPage = null,
        public array $sort = [],
    ) {}

    public function toQueryString(): string
    {
        $parts = [];

        if ($this->page !== null) {
            $parts[] = 'page=' . $this->page;
        }

        if ($this->perPage !== null) {
            $parts[] = 'per_page=' . $this->perPage;
        }

        foreach ($this->sort as $field => $direction) {
            $parts[] = 'sort[' . $field . ']=' . $direction->value;
        }

        return implode('&', $parts);
    }
}
