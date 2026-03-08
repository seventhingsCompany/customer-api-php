<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Rooms;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Rooms\RoomsService;

final class RoomsServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): RoomsService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new RoomsService($httpClient);
    }

    #[Test]
    public function listReturnsArray(): void
    {
        $data = [['uuid' => 'r1']];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $this->assertSame($data, $service->list());
        $this->assertStringEndsWith('/rooms', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function countReturnsInt(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '{"count":7}')]);

        $this->assertSame(7, $service->count());
        $this->assertStringEndsWith('/rooms/count', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function createReturnsUuid(): void
    {
        $service = $this->createService([
            new GuzzleResponse(201, ['Location' => '/customer-api/v1/room/room-uuid'], ''),
        ]);

        $this->assertSame('room-uuid', $service->create(['name' => 'Room A']));
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/room', (string) $request->getUri());
    }

    #[Test]
    public function getReturnsArray(): void
    {
        $data = ['uuid' => 'r1', 'name' => 'Room A'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $this->assertSame($data, $service->get('r1'));
        $this->assertStringEndsWith('/room/r1', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function patchReturnsUpdatedArray(): void
    {
        $updated = ['uuid' => 'r1', 'name' => 'Updated'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($updated))]);

        $result = $service->patch('r1', ['name' => 'Updated']);

        $this->assertSame($updated, $result);
        $request = $this->history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringEndsWith('/room/r1', (string) $request->getUri());
    }

    #[Test]
    public function deleteReturnsVoid(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->delete('r1');

        $request = $this->history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringEndsWith('/room/r1', (string) $request->getUri());
    }
}
