<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class FieldDefinition
{
    /**
     * FieldAttribute type marking a field as required when creating a
     * resource. Its value is "yes" when the field is mandatory.
     */
    public const ATTRIBUTE_MANDATORY = 'mandatory';

    /** The {@see ATTRIBUTE_MANDATORY} value that marks a field as required. */
    private const MANDATORY_VALUE = 'yes';

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

    /**
     * Returns the value of the named attribute, or null if it is not present.
     * Note a present attribute may itself carry a null value; use
     * {@see hasAttribute()} to disambiguate if needed.
     */
    public function attribute(string $type): mixed
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->type === $type) {
                return $attribute->value;
            }
        }
        return null;
    }

    /** Reports whether the named attribute is present. */
    public function hasAttribute(string $type): bool
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->type === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reports whether the field is configured as required for this instance.
     * Mandatory fields must be supplied when creating a resource
     * (object/asset, room, person) for this template, unless the key is
     * system-managed (see {@see SystemManagedFieldKeys}).
     */
    public function isMandatory(): bool
    {
        return $this->attribute(self::ATTRIBUTE_MANDATORY) === self::MANDATORY_VALUE;
    }
}
