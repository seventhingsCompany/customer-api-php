<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class FieldRelation
{
    /**
     * @param string[] $comparisonValues
     */
    public function __construct(
        public string $type,
        public string $fieldUUID,
        public array $comparisonValues = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            fieldUUID: $data['field_uuid'],
            comparisonValues: $data['comparison_values'] ?? [],
        );
    }

    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'field_uuid' => $this->fieldUUID,
        ];

        if ($this->comparisonValues !== []) {
            $result['comparison_values'] = $this->comparisonValues;
        }

        return $result;
    }
}
