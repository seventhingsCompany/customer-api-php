<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class PersonListResponse
{
    /**
     * @param PersonResponse[] $items
     * @param array<string, string> $sort field key => direction (ASC/DESC),
     *     as echoed by the API; empty when no sort was applied
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public array $sort,
        public int $total,
    ) {}

    public static function fromArray(array $data): self
    {
        // The API echoes an empty sort as [] (a JSON array) and a populated
        // one as {field: "ASC"} (a JSON object); both decode to a PHP array,
        // so normalize to an associative array<string, string>.
        $sort = $data['sort'] ?? [];

        return new self(
            items: array_map(fn(array $item) => PersonResponse::fromArray($item), $data['items'] ?? []),
            page: $data['page'] ?? 0,
            perPage: $data['per_page'] ?? 0,
            sort: is_array($sort) ? $sort : [],
            total: $data['total'] ?? 0,
        );
    }
}
