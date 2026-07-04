<?php

declare(strict_types=1);

namespace Seventhings\Models;

/**
 * System-managed field keys the server maintains itself. These may be reported
 * as mandatory by the field-definitions endpoint but must not be sent when
 * creating a resource — the server fills them in.
 */
final class SystemManagedFieldKeys
{
    /** @var list<string> */
    public const KEYS = [
        'id',
        'uuid',
        // Server-assigned resource identity keys. The API reports some of
        // these as mandatory (e.g. asset_uuid), but they are returned by the
        // server on create, never supplied by the caller.
        'person_uuid',
        'user_uuid',
        'asset_uuid',
        'room_uuid',
        'location_uuid',
        'created_at',
        'updated_at',
        'updated_by_user_id',
        'imported_by_user_id',
        'imported_with_template_id',
        'imported_at',
        'created_on_import_with_template_id',
    ];

    /** Reports whether $fieldKey is server-managed. */
    public static function contains(string $fieldKey): bool
    {
        return in_array($fieldKey, self::KEYS, true);
    }
}
