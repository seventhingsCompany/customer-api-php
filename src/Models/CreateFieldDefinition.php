<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class CreateFieldDefinition
{
    /**
     * @param FieldAttribute[] $attributes
     * @param FieldRelation[] $relations
     */
    public function __construct(
        public FieldDefinitionFieldType $fieldType,
        public string $label,
        public array $attributes = [],
        public array $relations = [],
        public ?string $comment = null,
        public mixed $defaultValue = null,
        public array $possibleValues = [],
    ) {}

    public function toArray(): array
    {
        $result = [
            'field_type' => $this->fieldType->toArray(),
            'label' => $this->label,
            'attributes' => array_map(fn(FieldAttribute $a) => $a->toArray(), $this->attributes),
            'relations' => array_map(fn(FieldRelation $r) => $r->toArray(), $this->relations),
            'possible_values' => $this->possibleValues,
        ];

        if ($this->comment !== null) {
            $result['comment'] = $this->comment;
        }

        if ($this->defaultValue !== null) {
            $result['default_value'] = $this->defaultValue;
        }

        return $result;
    }
}
