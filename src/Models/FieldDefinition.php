<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class FieldDefinition
{
    /**
     * @param FieldAttribute[] $attributes
     * @param FieldRelation[] $relations
     */
    public function __construct(
        public string $uuid,
        public string $fieldKey,
        public FieldDefinitionFieldType $fieldType,
        public string $label,
        public array $attributes,
        public array $relations,
        public ?string $comment,
        public mixed $defaultValue,
        public ?array $possibleValues,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            fieldKey: $data['field_key'],
            fieldType: FieldDefinitionFieldType::fromArray($data['field_type']),
            label: $data['label'],
            attributes: array_map(
                fn(array $a) => FieldAttribute::fromArray($a),
                $data['attributes'] ?? [],
            ),
            relations: array_map(
                fn(array $r) => FieldRelation::fromArray($r),
                $data['relations'] ?? [],
            ),
            comment: $data['comment'] ?? null,
            defaultValue: $data['default_value'] ?? null,
            possibleValues: $data['possible_values'] ?? null,
        );
    }
}
