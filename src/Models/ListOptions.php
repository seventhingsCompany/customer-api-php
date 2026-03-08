<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\SortDirection;

readonly class ListOptions
{
    /**
     * @param int|null $page
     * @param int|null $perPage
     * @param array<string, SortDirection> $sort
     * @param FilterEntry[] $filters
     */
    public function __construct(
        public ?int $page = null,
        public ?int $perPage = null,
        public array $sort = [],
        public array $filters = [],
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

        foreach ($this->filters as $filter) {
            if ($filter->isMultiValueOp()) {
                foreach ($filter->values as $value) {
                    $parts[] = 'filter[' . $filter->field . '][' . $filter->operator->value . '][]=' . rawurlencode($value);
                }
            } else {
                $value = $filter->values[0] ?? '';
                $parts[] = 'filter[' . $filter->field . '][' . $filter->operator->value . ']=' . rawurlencode($value);
            }
        }

        return implode('&', $parts);
    }
}
