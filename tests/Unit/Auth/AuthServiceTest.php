<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Auth;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\Auth\AuthService;
use Seventhings\HttpClient;
use Seventhings\Models\ApiException;
use Seventhings\Models\Enums\SSOAppTarget;
use Seventhings\Models\Enums\SSOProviderName;
use Seventhings\Models\TokenResponse;

final class AuthServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): AuthService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);

        return new AuthService($httpClient);
    }

    private function tokenBody(): string
    {
        return json_encode([
            'access_token' => 'at-123',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'read write',
            'refresh_token' => 'rt-456',
            'user_id' => 42,
        ]);
    }

    #[Test]
    public function loginHappyPath(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], $this->tokenBody())]);

        $token = $service->login('user@example.com', 'secret', 'my-client');

        $this->assertInstanceOf(TokenResponse::class, $token);
        $this->assertSame('at-123', $token->accessToken);
        $this->assertSame(3600, $token->expiresIn);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame('read write', $token->scope);
        $this->assertSame('rt-456', $token->refreshToken);
        $this->assertSame(42, $token->userId);

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/customer-api/v1/auth_token', (string) $request->getUri());
        $this->assertFalse($request->hasHeader('Authorization'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('password', $body['grant_type']);
        $this->assertSame('user@example.com', $body['username']);
        $this->assertSame('secret', $body['password']);
        $this->assertSame('my-client', $body['client_id']);
    }

    #[Test]
    public function loginForbiddenThrowsApiException(): void
    {
        $service = $this->createService([new GuzzleResponse(403, [], '{"detail":"invalid credentials"}')]);

        $this->expectException(ApiException::class);
        $service->login('bad@user.com', 'wrong', 'cid');
    }

    #[Test]
    public function loginSSOWithoutAppTarget(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], $this->tokenBody())]);

        $token = $service->loginSSO(SSOProviderName::AzureOpenIdConnect, 'code-abc', 'cid');

        $this->assertSame('at-123', $token->accessToken);

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('sso', $body['grant_type']);
        $this->assertSame('azure-open-id-connect', $body['provider']);
        $this->assertSame('code-abc', $body['auth_code']);
        $this->assertArrayNotHasKey('app_target', $body);
    }

    #[Test]
    public function loginSSOWithAppTarget(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], $this->tokenBody())]);

        $service->loginSSO(SSOProviderName::GoogleOpenIdConnect, 'code-xyz', 'cid', SSOAppTarget::Mobile);

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('mobile', $body['app_target']);
    }

    #[Test]
    public function refreshHappyPath(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], $this->tokenBody())]);

        $token = $service->refresh('rt-old');

        $this->assertSame('at-123', $token->accessToken);

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('refresh_token', $body['grant_type']);
        $this->assertSame('rt-old', $body['refresh_token']);
        $this->assertFalse($this->history[0]['request']->hasHeader('Authorization'));
    }

    #[Test]
    public function revokeTokensSendsAuthenticatedDelete(): void
    {
        $mock = new MockHandler([new GuzzleResponse(204, [], '')]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('my-token');
        $service = new AuthService($httpClient);

        $service->revokeTokens();

        $request = $this->history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringEndsWith('/customer-api/v1/auth_token', (string) $request->getUri());
        $this->assertSame('Bearer my-token', $request->getHeaderLine('Authorization'));
    }

    #[Test]
    public function pingSendsUnauthenticatedGet(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '{}')]);

        $service->ping();

        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringEndsWith('/customer-api/v1', (string) $request->getUri());
        $this->assertFalse($request->hasHeader('Authorization'));
    }
}
