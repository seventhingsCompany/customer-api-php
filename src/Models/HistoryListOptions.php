<?php

declare(strict_types=1);

namespace Seventhings\Models;

/**
 * History pagination. Unset values use the API defaults (page 1, per_page 50).
 * The API allows at most 200 entries per page.
 */
readonly class HistoryListOptions
{
    public function __construct(
        public ?int $page = null,
        public ?int $perPage = null,
    ) {}

    public function toQueryString(): string
    {
        return http_build_query([
            'page' => $this->page,
            'per_page' => $this->perPage,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
