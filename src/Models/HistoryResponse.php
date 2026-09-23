<?php

declare(strict_types=1);

namespace Seventhings\Models;

/**
 * A page of recorded changes, newest first.
 *
 * @template T
 */
readonly class HistoryResponse
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}

    /**
     * @template TItem
     * @param callable(array<string, mixed>): TItem $decodeItem
     * @return self<TItem>
     */
    public static function fromArray(array $data, callable $decodeItem): self
    {
        return new self(
            items: array_map($decodeItem, $data['items'] ?? []),
            page: $data['page'] ?? 0,
            perPage: $data['per_page'] ?? 0,
            total: $data['total'] ?? 0,
        );
    }
}
