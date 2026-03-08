<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class UserListResponse
{
    /**
     * @param UserResponse[] $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public string $sortBy,
        public string $order,
        public int $total,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            items: array_map(fn(array $item) => UserResponse::fromArray($item), $data['items']),
            page: $data['page'],
            perPage: $data['per_page'],
            sortBy: $data['sort_by'],
            order: $data['order'],
            total: $data['total'],
        );
    }
}
