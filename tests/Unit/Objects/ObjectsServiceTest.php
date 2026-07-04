<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Objects;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Models\ApiException;
use Seventhings\Models\Fields;
use Seventhings\Models\FileAttachment;
use Seventhings\Models\ListOptions;
use Seventhings\Objects\ObjectsService;

final class ObjectsServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): ObjectsService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new ObjectsService($httpClient);
    }

    #[Test]
    public function listReturnsArray(): void
    {
        $items = [['uuid' => 'a'], ['uuid' => 'b']];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode(['items' => $items]))]);

        $result = $service->list();

        $this->assertSame($items, $result);
        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringEndsWith('/objects', (string) $request->getUri());
    }

    #[Test]
    public function allWalksPagesAndWrapsInFields(): void
    {
        // perPage=2: a full page (2) then a short page (1) ends iteration.
        $service = $this->createService([
            new GuzzleResponse(200, [], json_encode(['items' => [['uuid' => 'a'], ['uuid' => 'b']]])),
            new GuzzleResponse(200, [], json_encode(['items' => [['uuid' => 'c']]])),
        ]);

        $items = iterator_to_array($service->all(new ListOptions(perPage: 2)), false);

        $this->assertCount(3, $items);
        $this->assertContainsOnlyInstancesOf(Fields::class, $items);
        $this->assertSame(['a', 'b', 'c'], array_map(fn(Fields $f) => $f->uuid(), $items));

        // Two requests, incrementing pages, page size preserved.
        $this->assertCount(2, $this->history);
        $this->assertStringContainsString('page=1', (string) $this->history[0]['request']->getUri());
        $this->assertStringContainsString('per_page=2', (string) $this->history[0]['request']->getUri());
        $this->assertStringContainsString('page=2', (string) $this->history[1]['request']->getUri());
    }

    #[Test]
    public function allStopsOnEmptyFirstPage(): void
    {
        // A full page exactly filling perPage, then an empty page.
        $service = $this->createService([
            new GuzzleResponse(200, [], json_encode(['items' => [['uuid' => 'a']]])),
            new GuzzleResponse(200, [], json_encode(['items' => []])),
        ]);

        $items = iterator_to_array($service->all(new ListOptions(perPage: 1)), false);

        $this->assertCount(1, $items);
        $this->assertCount(2, $this->history);
    }

    #[Test]
    public function listWithOptionsAppendsQueryString(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '{"items":[]}')]);

        $options = new \Seventhings\Models\ListOptions(page: 2, perPage: 10);
        $service->list($options);

        $uri = (string) $this->history[0]['request']->getUri();
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('per_page=10', $uri);
    }

    #[Test]
    public function countReturnsInt(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '{"count":42}')]);

        $this->assertSame(42, $service->count());
        $this->assertStringEndsWith('/objects/count', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function createReturnsUuid(): void
    {
        $service = $this->createService([
            new GuzzleResponse(201, ['Location' => '/customer-api/v1/object/abc-123'], ''),
        ]);

        $uuid = $service->create(['name' => 'Test']);

        $this->assertSame('abc-123', $uuid);
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/object', (string) $request->getUri());
    }

    #[Test]
    public function getReturnsArray(): void
    {
        $data = ['uuid' => 'abc', 'name' => 'Widget'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->get('abc');

        $this->assertSame($data, $result);
        $this->assertStringEndsWith('/object/abc', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function patchReturnsVoid(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->patch('abc', ['name' => 'Updated']);

        $request = $this->history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringEndsWith('/object/abc', (string) $request->getUri());
    }

    #[Test]
    public function deleteReturnsVoid(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->delete('abc');

        $request = $this->history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringEndsWith('/object/abc', (string) $request->getUri());
    }

    #[Test]
    public function archiveSendsPost(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '{}')]);

        $service->archive('abc');

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/object/abc/archive', (string) $request->getUri());
    }

    #[Test]
    public function unarchiveSendsPost(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '{}')]);

        $service->unarchive('abc');

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/object/abc/unarchive', (string) $request->getUri());
    }

    #[Test]
    public function addFilesReturnsResponse(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '{"status":"ok"}')]);

        $attachments = [
            new FileAttachment('field-1', 'uuid-aaa'),
            new FileAttachment('field-2', 'uuid-bbb'),
        ];
        $response = $service->addFiles('abc', $attachments);

        $this->assertSame(200, $response->statusCode);

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/object/abc/add-file', (string) $request->getUri());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame([
            ['field-key' => 'field-1', 'file-uuid' => 'uuid-aaa'],
            ['field-key' => 'field-2', 'file-uuid' => 'uuid-bbb'],
        ], $body);
    }

    #[Test]
    public function removeFilesReturnsResponse(): void
    {
        $service = $this->createService([new GuzzleResponse(207, [], '{"results":"mixed"}')]);

        $attachments = [new FileAttachment('f1', 'u1')];
        $response = $service->removeFiles('abc', $attachments);

        $this->assertSame(207, $response->statusCode);
        $this->assertStringEndsWith('/object/abc/remove-file', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function notFoundThrowsApiException(): void
    {
        $service = $this->createService([new GuzzleResponse(404, [], '{"error":"not found"}')]);

        $this->expectException(ApiException::class);
        $service->get('nonexistent');
    }
}
