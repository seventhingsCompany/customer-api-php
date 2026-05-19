<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\UserSortOrder;

/**
 * PersonListOptions configures pagination and sorting for person list
 * requests. `sortBy` is a free-form field key (see field definitions);
 * `order` reuses UserSortOrder.
 */
readonly class PersonListOptions
{
    public function __construct(
        public ?int $page = null,
        public ?int $perPage = null,
        public ?string $sortBy = null,
        public ?UserSortOrder $order = null,
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

        if ($this->sortBy !== null) {
            $parts[] = 'sort_by=' . rawurlencode($this->sortBy);
        }

        if ($this->order !== null) {
            $parts[] = 'order=' . rawurlencode($this->order->value);
        }

        return implode('&', $parts);
    }
}
