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

    /** Matches values equal to $value. */
    public static function eq(string $field, string $value): self
    {
        return new self($field, FilterOperator::Eq, [$value]);
    }

    /** Matches values not equal to $value. */
    public static function neq(string $field, string $value): self
    {
        return new self($field, FilterOperator::Neq, [$value]);
    }

    /** Matches values greater than $value. */
    public static function gt(string $field, string $value): self
    {
        return new self($field, FilterOperator::Gt, [$value]);
    }

    /** Matches values greater than or equal to $value. */
    public static function gte(string $field, string $value): self
    {
        return new self($field, FilterOperator::Gte, [$value]);
    }

    /** Matches values less than $value. */
    public static function lt(string $field, string $value): self
    {
        return new self($field, FilterOperator::Lt, [$value]);
    }

    /** Matches values less than or equal to $value. */
    public static function lte(string $field, string $value): self
    {
        return new self($field, FilterOperator::Lte, [$value]);
    }

    /** Matches values containing $value. */
    public static function like(string $field, string $value): self
    {
        return new self($field, FilterOperator::Like, [$value]);
    }

    /** Matches values not containing $value. */
    public static function notLike(string $field, string $value): self
    {
        return new self($field, FilterOperator::NotLike, [$value]);
    }

    /** Matches values present in the given set. */
    public static function in(string $field, string ...$values): self
    {
        return new self($field, FilterOperator::In, $values);
    }

    /** Matches values not present in the given set. */
    public static function nin(string $field, string ...$values): self
    {
        return new self($field, FilterOperator::Nin, $values);
    }

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
