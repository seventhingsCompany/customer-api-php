<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Models\ApiException;
use Seventhings\Models\NetworkException;

final class HttpClientTest extends TestCase
{
    private array $history = [];

    private function createClient(array $responses): HttpClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        return new HttpClient('https://example.com', $guzzle);
    }

    #[Test]
    public function baseUrlConstruction(): void
    {
        $client = $this->createClient([new GuzzleResponse(200, [], '{}')]);
        $client->setToken('tok');
        $client->get('objects');

        $request = $this->history[0]['request'];
        $this->assertSame(
            'https://example.com/customer-api/v1/objects',
            (string) $request->getUri(),
        );
    }

    #[Test]
    public function baseUrlTrimsTrailingSlash(): void
    {
        $mock = new MockHandler([new GuzzleResponse(200, [], '{}')]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        $client = new HttpClient('https://example.com/', $guzzle);
        $client->setToken('tok');
        $client->get('ping');

        $request = $this->history[0]['request'];
        $this->assertStringStartsWith('https://example.com/customer-api/v1/', (string) $request->getUri());
    }

    #[Test]
    public function authenticatedRequestHasBearerHeader(): void
    {
        $client = $this->createClient([new GuzzleResponse(200, [], '{}')]);
        $client->setToken('my-jwt-token');
        $client->get('test');

        $request = $this->history[0]['request'];
        $this->assertSame('Bearer my-jwt-token', $request->getHeaderLine('Authorization'));
    }

    #[Test]
    public function unauthenticatedRequestHasNoAuthHeader(): void
    {
        $client = $this->createClient([new GuzzleResponse(200, [], '{}')]);
        $client->setToken('my-jwt-token');
        $client->getUnauthenticated('auth/login');

        $request = $this->history[0]['request'];
        $this->assertFalse($request->hasHeader('Authorization'));
    }

    #[Test]
    public function postEncodesJsonBody(): void
    {
        $client = $this->createClient([new GuzzleResponse(201, [], '{}')]);
        $client->setToken('tok');
        $client->post('items', ['name' => 'test', 'count' => 5]);

        $request = $this->history[0]['request'];
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('{"name":"test","count":5}', (string) $request->getBody());
    }

    #[Test]
    public function fourHundredThrowsApiException(): void
    {
        $client = $this->createClient([new GuzzleResponse(404, [], '{"error":"not found"}')]);
        $client->setToken('tok');

        try {
            $client->get('missing');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(404, $e->statusCode);
            $this->assertSame('Not Found', $e->status);
            $this->assertSame('{"error":"not found"}', $e->body);
        }
    }

    #[Test]
    public function fiveHundredThrowsApiException(): void
    {
        $client = $this->createClient([new GuzzleResponse(500, [], 'server error')]);
        $client->setToken('tok');

        $this->expectException(ApiException::class);
        $client->get('broken');
    }

    #[Test]
    public function connectExceptionThrowsNetworkException(): void
    {
        $client = $this->createClient([
            new ConnectException('Connection refused', new Request('GET', 'test')),
        ]);
        $client->setToken('tok');

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Connection refused');
        $client->get('unreachable');
    }

    #[Test]
    public function getTokenAndSetToken(): void
    {
        $client = $this->createClient([]);
        $this->assertSame('', $client->getToken());

        $client->setToken('abc');
        $this->assertSame('abc', $client->getToken());
    }

    #[Test]
    public function authenticatedGetAcceptsJson(): void
    {
        $client = $this->createClient([new GuzzleResponse(200, [], '[]')]);
        $client->setToken('tok');
        $client->get('items');

        $request = $this->history[0]['request'];
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    #[Test]
    public function postUnauthenticatedSendsNoAuth(): void
    {
        $client = $this->createClient([new GuzzleResponse(200, [], '{}')]);
        $client->setToken('tok');
        $client->postUnauthenticated('auth/token', ['user' => 'test']);

        $request = $this->history[0]['request'];
        $this->assertFalse($request->hasHeader('Authorization'));
        $this->assertSame('{"user":"test"}', (string) $request->getBody());
    }
}
