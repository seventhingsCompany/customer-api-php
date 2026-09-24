<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Reports;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Models\ApiException;
use Seventhings\Models\CreateReportRequest;
use Seventhings\Models\ReportTemplate;
use Seventhings\Reports\ReportsService;

final class ReportsServiceTest extends TestCase
{
    private array $history = [];

    private function createService(GuzzleResponse ...$responses): ReportsService
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $http = new HttpClient('https://example.com', new GuzzleClient(['handler' => $stack]));
        $http->setToken('report-token');

        return new ReportsService($http);
    }

    #[Test]
    public function listsTemplatesAndEmptyResponses(): void
    {
        $service = $this->createService(
            new GuzzleResponse(200, [], '[{"uuid":"template-uuid","name":"Inventory list"}]'),
            new GuzzleResponse(200, [], '[]'),
        );
        $this->assertEquals([new ReportTemplate('template-uuid', 'Inventory list')], $service->listTemplates());
        $this->assertSame([], $service->listTemplates());

        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/customer-api/v1/report-template', $request->getUri()->getPath());
        $this->assertSame('', $request->getUri()->getQuery());
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertSame('Bearer report-token', $request->getHeaderLine('Authorization'));
    }

    #[Test]
    public function createReturnsBinaryPdfAndPreservesObjectOrder(): void
    {
        $pdf = "%PDF-1.7\n\x00\xff\n%%EOF";
        $service = $this->createService(
            new GuzzleResponse(200, ['Content-Type' => 'application/pdf'], $pdf),
            new GuzzleResponse(200, [], '[]'),
        );
        $this->assertSame($pdf, $service->create(new CreateReportRequest('template-uuid', ['object-2', 'object-1'])));

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/customer-api/v1/report', $request->getUri()->getPath());
        $this->assertSame('application/pdf', $request->getHeaderLine('Accept'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('Bearer report-token', $request->getHeaderLine('Authorization'));
        $this->assertSame([
            'report_template_uuid' => 'template-uuid',
            'object_uuids' => ['object-2', 'object-1'],
        ], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));

        $service->listTemplates();
        $this->assertSame('application/json', $this->history[1]['request']->getHeaderLine('Accept'));
    }

    #[Test]
    public function malformedTemplatesThrow(): void
    {
        $service = $this->createService(new GuzzleResponse(200, [], '{'));
        $this->expectException(\JsonException::class);
        $service->listTemplates();
    }

    public static function apiErrors(): iterable
    {
        foreach (['listTemplates', 'create'] as $operation) {
            foreach ([400, 401, 403, 404, 500] as $status) {
                yield "$operation/$status" => [$operation, $status];
            }
        }
    }

    #[Test]
    #[DataProvider('apiErrors')]
    public function preservesApiErrors(string $operation, int $status): void
    {
        $body = '{"message":"report failed"}';
        $service = $this->createService(new GuzzleResponse($status, [], $body));
        try {
            if ($operation === 'create') {
                $service->create(new CreateReportRequest('template', ['object']));
            } else {
                $service->listTemplates();
            }
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame($status, $e->statusCode);
            $this->assertSame($body, $e->body);
        }
    }
}
