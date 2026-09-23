<?php

declare(strict_types=1);

namespace Seventhings\Reports;

use Seventhings\HttpClient;
use Seventhings\Models\CreateReportRequest;
use Seventhings\Models\ReportTemplate;

final class ReportsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    /** @return ReportTemplate[] All PDF templates available on the instance. */
    public function listTemplates(): array
    {
        return array_map(
            fn(array $item) => ReportTemplate::fromArray($item),
            $this->httpClient->get('report-template')->json(),
        );
    }

    /**
     * Renders objects into a template and returns the binary PDF string.
     * The API generates a new document on each call and does not store it.
     */
    public function create(CreateReportRequest $request): string
    {
        return $this->httpClient->post('report', $request->toArray(), 'application/pdf')->body;
    }
}
