<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class AddObjectEntry
{
    public function __construct(
        public string $category,
        public string $price,
    ) {}

    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'price' => $this->price,
        ];
    }
}
