<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class CreateReportRequest
{
    /** @param list<string> $objectUuids At least one object, in render order. */
    public function __construct(
        public string $reportTemplateUuid,
        public array $objectUuids,
    ) {}

    public function toArray(): array
    {
        return [
            'report_template_uuid' => $this->reportTemplateUuid,
            'object_uuids' => $this->objectUuids,
        ];
    }
}
