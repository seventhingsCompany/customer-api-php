<?php

declare(strict_types=1);

namespace Seventhings\Models;

use Seventhings\Models\Enums\FieldTypeName;

readonly class FieldDefinitionFieldType
{
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
}
