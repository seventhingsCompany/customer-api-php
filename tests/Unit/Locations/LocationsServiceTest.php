<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Locations;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Locations\LocationsService;

final class LocationsServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): LocationsService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new LocationsService($httpClient);
    }

    #[Test]
    public function listReturnsArray(): void
    {
        $items = [['uuid' => 'loc1']];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode(['items' => $items]))]);

        $this->assertSame($items, $service->list());
        $this->assertStringEndsWith('/locations', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function countReturnsInt(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '{"count":15}')]);

        $this->assertSame(15, $service->count());
        $this->assertStringEndsWith('/locations/count', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function createReturnsUuid(): void
    {
        $service = $this->createService([
            new GuzzleResponse(201, ['Location' => '/customer-api/v1/location/loc-uuid'], ''),
        ]);

        $this->assertSame('loc-uuid', $service->create(['name' => 'Building A']));
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/location', (string) $request->getUri());
    }

    #[Test]
    public function getReturnsArray(): void
    {
        $data = ['uuid' => 'loc1', 'name' => 'Building A'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $this->assertSame($data, $service->get('loc1'));
        $this->assertStringEndsWith('/location/loc1', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function patchReturnsUpdatedArray(): void
    {
        $updated = ['uuid' => 'loc1', 'name' => 'Updated'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($updated))]);

        $result = $service->patch('loc1', ['name' => 'Updated']);

        $this->assertSame($updated, $result);
        $request = $this->history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringEndsWith('/location/loc1', (string) $request->getUri());
    }

    #[Test]
    public function deleteReturnsVoid(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->delete('loc1');

        $request = $this->history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringEndsWith('/location/loc1', (string) $request->getUri());
    }
}
