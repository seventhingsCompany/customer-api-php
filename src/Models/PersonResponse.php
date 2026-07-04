<?php

declare(strict_types=1);

namespace Seventhings\Models;

/**
 * Person represents a person in the seventhings asset-tracking system.
 *
 * Field names follow the live API response, which uses snake_case and
 * differs from the OpenAPI spec (the spec documents `uuid`/`firstname`/
 * `lastname`, but the wire format is `person_uuid`/`first_name`/`last_name`).
 *
 * Person fields are template-defined: the API returns each field as a flat
 * top-level key named by its template `field_key`. The named constructor
 * properties below are typed conveniences for the common fields, but a
 * template may define additional custom fields. To avoid losing those, the
 * full untouched wire map is preserved in `$fields`; read custom fields via
 * `$fields['some_key']` or the null-safe `field('some_key')` accessor.
 */
readonly class PersonResponse
{
    /**
     * @param array<string, mixed> $fields The complete raw field map from the
     *     API, including template-defined custom fields not surfaced as named
     *     properties above.
     */
    public function __construct(
        public string $uuid,
        public int $id,
        public string $userUuid,
        public string $email,
        public ?string $firstname,
        public ?string $lastname,
        public ?string $department,
        public ?array $picture,
        public ?array $documents,
        public ?int $updatedByUserId,
        public ?string $updatedAt,
        public ?string $createdAt,
        public ?int $importedByUserId,
        public ?int $importedWithTemplateId,
        public ?string $importedAt,
        public ?int $createdOnImportWithTemplateId,
        public array $fields = [],
    ) {}

    public function field(string $key): mixed
    {
        return $this->fields[$key] ?? null;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['person_uuid'] ?? '',
            id: $data['id'] ?? 0,
            userUuid: $data['user_uuid'] ?? '',
            email: $data['email'] ?? '',
            firstname: $data['first_name'] ?? null,
            lastname: $data['last_name'] ?? null,
            department: $data['department'] ?? null,
            picture: $data['picture'] ?? null,
            documents: $data['documents'] ?? null,
            updatedByUserId: $data['updated_by_user_id'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            createdAt: $data['created_at'] ?? null,
            importedByUserId: $data['imported_by_user_id'] ?? null,
            importedWithTemplateId: $data['imported_with_template_id'] ?? null,
            importedAt: $data['imported_at'] ?? null,
            createdOnImportWithTemplateId: $data['created_on_import_with_template_id'] ?? null,
            fields: $data,
        );
    }
}
