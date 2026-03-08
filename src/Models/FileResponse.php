<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class FileResponse
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $type,
        public int $size,
        public int $creatorId,
        public string $createdAt,
        public string $dataUri,
        public string $thumbnailUri,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            name: $data['name'],
            type: $data['type'],
            size: $data['size'],
            creatorId: $data['creator_id'],
            createdAt: $data['created_at'],
            dataUri: $data['data_uri'],
            thumbnailUri: $data['thumbnail_uri'],
        );
    }
}
