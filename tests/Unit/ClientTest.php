<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\Auth\AuthService;
use Seventhings\Client;
use Seventhings\HttpClient;
use Seventhings\Locations\LocationsService;
use Seventhings\Objects\ObjectsService;
use Seventhings\Rooms\RoomsService;

final class ClientTest extends TestCase
{
    #[Test]
    public function withTokenConstructs(): void
    {
        $client = Client::withToken('https://example.com', 'my-token');
        $this->assertInstanceOf(Client::class, $client);
        $this->assertInstanceOf(HttpClient::class, $client->getHttpClient());
    }

    #[Test]
    public function withTokenSetsToken(): void
    {
        $client = Client::withToken('https://example.com', 'my-token');
        $this->assertSame('my-token', $client->getHttpClient()->getToken());
    }

    #[Test]
    public function setTokenUpdatesHttpClient(): void
    {
        $client = Client::withToken('https://example.com', 'old-token');
        $client->setToken('new-token');
        $this->assertSame('new-token', $client->getHttpClient()->getToken());
    }

    #[Test]
    public function servicePropertiesAreCorrectTypes(): void
    {
        $client = Client::withToken('https://example.com', 'tok');

        $this->assertInstanceOf(AuthService::class, $client->auth);
        $this->assertInstanceOf(ObjectsService::class, $client->objects);
        $this->assertInstanceOf(RoomsService::class, $client->rooms);
        $this->assertInstanceOf(LocationsService::class, $client->locations);
    }

    #[Test]
    public function withCredentialsCallsLoginAndSetsToken(): void
    {
        $history = [];
        $tokenBody = json_encode([
            'access_token' => 'at-from-login',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => null,
            'refresh_token' => 'rt-xxx',
            'user_id' => 1,
        ]);

        $mock = new MockHandler([new GuzzleResponse(200, [], $tokenBody)]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        // Use reflection to inject the mock guzzle into withCredentials
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $client = new \ReflectionClass(Client::class);
        $instance = $client->newInstanceWithoutConstructor();

        // Manually invoke constructor logic
        $prop = $client->getProperty('httpClient');
        $prop->setValue($instance, $httpClient);

        $authProp = $client->getProperty('auth');
        $authProp->setValue($instance, new AuthService($httpClient));
        $objectsProp = $client->getProperty('objects');
        $objectsProp->setValue($instance, new ObjectsService($httpClient));
        $roomsProp = $client->getProperty('rooms');
        $roomsProp->setValue($instance, new RoomsService($httpClient));
        $locationsProp = $client->getProperty('locations');
        $locationsProp->setValue($instance, new LocationsService($httpClient));

        // Now call login through the auth service
        $token = $instance->auth->login('user', 'pass', 'cid');
        $instance->setToken($token->accessToken);

        $this->assertSame('at-from-login', $httpClient->getToken());

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('password', $body['grant_type']);
    }
}
