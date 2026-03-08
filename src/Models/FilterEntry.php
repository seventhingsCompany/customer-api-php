<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\FilterOperator;

readonly class FilterEntry
{
    public function __construct(
        public string $field,
        public FilterOperator $operator,
        public array $values,
    ) {}

    public function isMultiValueOp(): bool
    {
        return in_array($this->operator, [
            FilterOperator::Like,
            FilterOperator::NotLike,
            FilterOperator::In,
            FilterOperator::Nin,
        ], true);
    }
}
