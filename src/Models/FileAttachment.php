<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class FileAttachment
{
    public function __construct(
        public string $fieldKey,
        public string $fileUuid,
    ) {}

    public function toArray(): array
    {
        return [
            'field-key' => $this->fieldKey,
            'file-uuid' => $this->fileUuid,
        ];
    }
}
