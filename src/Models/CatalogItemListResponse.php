<?php

declare(strict_types=1);

namespace Seventhings\Models;

/** A paginated product catalog response. */
readonly class CatalogItemListResponse
{
    /**
     * @param CatalogItem[] $items
     * @param array<string, string> $sort field key => direction (ASC/DESC), as
     *     echoed by the API; empty when no sort was applied
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
        public array $sort,
    ) {}

    public static function fromArray(array $data): self
    {
        // The API emits an empty sort as [] instead of {}; both decode to an
        // empty PHP array. Non-empty lists and scalars are invalid.
        $sort = $data['sort'] ?? [];
        if (!is_array($sort) || ($sort !== [] && array_is_list($sort))) {
            throw new \UnexpectedValueException('catalog item sort must be an object');
        }

        return new self(
            items: array_map(fn(array $item) => CatalogItem::fromArray($item), $data['items'] ?? []),
            page: $data['page'] ?? 0,
            perPage: $data['per_page'] ?? 0,
            total: $data['total'] ?? 0,
            sort: $sort,
        );
    }
}
