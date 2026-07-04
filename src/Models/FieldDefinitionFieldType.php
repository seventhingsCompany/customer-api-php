<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\FieldTypeName;

readonly class FieldDefinitionFieldType
{
    /**
     * FieldValueConstraint type that enumerates the permitted values for a
     * constrained field (e.g. a DROPDOWN).
     */
    public const CONSTRAINT_ALLOWED_VALUES = 'allowed_values';

    /**
     * @param FieldValueConstraint[] $constraints
     */
    public function __construct(
        public FieldTypeName $name,
        public array $constraints = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: FieldTypeName::from($data['name']),
            constraints: array_map(
                fn(array $c) => FieldValueConstraint::fromArray($c),
                $data['constraints'] ?? [],
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name->value,
            'constraints' => array_map(fn(FieldValueConstraint $c) => $c->toArray(), $this->constraints),
        ];
    }

    /**
     * Returns the permitted values for a constrained field (e.g. a DROPDOWN),
     * or null if no allowed-values constraint is present.
     *
     * @return list<mixed>|null
     */
    public function allowedValues(): ?array
    {
        foreach ($this->constraints as $constraint) {
            if ($constraint->type === self::CONSTRAINT_ALLOWED_VALUES && is_array($constraint->value)) {
                return array_values($constraint->value);
            }
        }
        return null;
    }
}
