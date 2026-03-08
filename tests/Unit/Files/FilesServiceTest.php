<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Files;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\Files\FilesService;
use Seventhings\HttpClient;
use Seventhings\Models\FileResponse;

final class FilesServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): FilesService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new FilesService($httpClient);
    }

    #[Test]
    public function listReturnsFileResponseArray(): void
    {
        $data = [
            'items' => [
                [
                    'uuid' => 'f1',
                    'name' => 'photo.jpg',
                    'type' => 'image/jpeg',
                    'size' => 1024,
                    'creator_id' => 1,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'data_uri' => '/file/f1/data',
                    'thumbnail_uri' => '/file/f1/thumbnail',
                ],
            ],
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->list();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(FileResponse::class, $result[0]);
        $this->assertSame('f1', $result[0]->uuid);
        $this->assertSame('photo.jpg', $result[0]->name);
        $this->assertSame(1024, $result[0]->size);
        $this->assertSame(1, $result[0]->creatorId);
        $this->assertStringEndsWith('/files', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function getReturnsFileResponse(): void
    {
        $data = [
            'uuid' => 'f1',
            'name' => 'doc.pdf',
            'type' => 'application/pdf',
            'size' => 2048,
            'creator_id' => 5,
            'created_at' => '2024-02-01T00:00:00Z',
            'data_uri' => '/file/f1/data',
            'thumbnail_uri' => '/file/f1/thumbnail',
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->get('f1');

        $this->assertInstanceOf(FileResponse::class, $result);
        $this->assertSame('f1', $result->uuid);
        $this->assertStringEndsWith('/file/f1', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function uploadReturnsUuidFromLocationUuidHeader(): void
    {
        $service = $this->createService([
            new GuzzleResponse(201, ['Location-UUID' => 'file-uuid-123'], ''),
        ]);

        $uuid = $service->upload('test.txt', 'file contents');

        $this->assertSame('file-uuid-123', $uuid);

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/file', (string) $request->getUri());
        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertStringContainsString('multipart/form-data', $contentType);
    }

    #[Test]
    public function uploadFallsBackToLocationHeader(): void
    {
        $service = $this->createService([
            new GuzzleResponse(201, ['Location' => '/customer-api/v1/file/fallback-uuid'], ''),
        ]);

        $uuid = $service->upload('test.txt', 'file contents');

        $this->assertSame('fallback-uuid', $uuid);
    }

    #[Test]
    public function getDataReturnsRawBody(): void
    {
        $binaryData = "\x00\x01\x02\x03";
        $service = $this->createService([new GuzzleResponse(200, [], $binaryData)]);

        $result = $service->getData('f1');

        $this->assertSame($binaryData, $result);
        $this->assertStringEndsWith('/file/f1/data', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function getThumbnailReturnsRawBody(): void
    {
        $thumbnailData = 'thumb-bytes';
        $service = $this->createService([new GuzzleResponse(200, [], $thumbnailData)]);

        $result = $service->getThumbnail('f1');

        $this->assertSame($thumbnailData, $result);
        $this->assertStringEndsWith('/file/f1/thumbnail', (string) $this->history[0]['request']->getUri());
    }
}
