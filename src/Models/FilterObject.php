<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class FilterObject
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $sort
     */
    public function __construct(
        public array $filter = [],
        public array $sort = [],
    ) {}

    public function toArray(): array
    {
        return [
            'filter' => (object) $this->filter,
            'sort' => (object) $this->sort,
        ];
    }
}
