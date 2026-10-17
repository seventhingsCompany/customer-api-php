<?php

declare(strict_types=1);

namespace Seventhings\Models;

/**
 * CatalogItem contains reusable object master data. Object field keys have a
 * `catalog_object_` prefix; catalog metadata such as `id` has no prefix.
 */
readonly class CatalogItem
{
    public function __construct(
        public string $uuid,
        public Fields $fields,
    ) {}

    public static function fromArray(array $data): self
    {
        $fields = $data['fields'] ?? [];
        if (!is_array($fields)) {
            throw new \UnexpectedValueException('catalog item fields must be an object');
        }

        return new self(
            uuid: $data['uuid'] ?? '',
            fields: new Fields($fields),
        );
    }
}
