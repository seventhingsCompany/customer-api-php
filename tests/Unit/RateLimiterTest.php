<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\Client;
use Seventhings\HttpClient;
use Seventhings\Models\ApiException;
use Seventhings\RateLimiter;

final class RateLimiterTest extends TestCase
{
    private float $now = 100.0;

    /** @var float[] */
    private array $sleeps = [];

    private function limiter(int $requestsPerMinute): ?RateLimiter
    {
        return RateLimiter::perMinute(
            $requestsPerMinute,
            fn(): float => $this->now,
            function (float $seconds): void {
                $this->sleeps[] = round($seconds, 6);
                $this->now += $seconds;
            },
        );
    }

    #[Test]
    public function spacesRequestsEvenlyAndFirstIsImmediate(): void
    {
        $limiter = $this->limiter(200);
        for ($i = 0; $i < 4; $i++) {
            $limiter->wait();
        }
        $this->assertSame([0.3, 0.3, 0.3], $this->sleeps);
    }

    #[Test]
    public function idleTimeDoesNotAccumulateBurst(): void
    {
        $limiter = $this->limiter(60);
        $limiter->wait();
        $this->now += 10;
        $limiter->wait();
        $limiter->wait();
        $this->assertSame([1.0], $this->sleeps);
    }

    #[Test]
    public function partiallyElapsedIntervalWaitsForRemainder(): void
    {
        $limiter = $this->limiter(60);
        $limiter->wait();
        $this->now += 0.25;
        $limiter->wait();
        $this->assertSame([0.75], $this->sleeps);
    }

    #[Test]
    public function zeroOrNegativeDisablesThrottling(): void
    {
        $this->assertNull(RateLimiter::perMinute(0));
        $this->assertNull(RateLimiter::perMinute(-5));
    }

    #[Test]
    public function sharedAcrossRequestTypesIncludingErrors(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(200, [], '{}'),
            new GuzzleResponse(200, [], 'raw'),
            new GuzzleResponse(201, ['Location-UUID' => 'f'], ''),
            new GuzzleResponse(200, [], '{"access_token":"a","expires_in":1,"token_type":"Bearer","scope":null,"refresh_token":"r"}'),
            new GuzzleResponse(404, [], ''),
            new GuzzleResponse(200, [], '{}'),
        ]));
        $http = new HttpClient('https://example.com', new GuzzleClient(['handler' => $stack]), $this->limiter(120));

        $http->get('objects');
        $http->getRaw('file/f/data');
        $http->postMultipart('file', [['name' => 'data', 'contents' => 'x', 'filename' => 'x']]);
        $http->postFormUnauthenticated('auth_token', ['grant_type' => 'authorization_code']);
        try {
            $http->delete('object/x');
        } catch (ApiException) {
        }
        $http->patch('object/x', []);

        $this->assertSame([0.5, 0.5, 0.5, 0.5, 0.5], $this->sleeps);
    }

    #[Test]
    public function clientFactoriesConfigureIndependentLimiters(): void
    {
        $limiterOf = fn(Client $c): ?RateLimiter => (new \ReflectionProperty(HttpClient::class, 'rateLimiter'))->getValue($c->getHttpClient());
        $interval = fn(RateLimiter $l): float => (new \ReflectionProperty(RateLimiter::class, 'interval'))->getValue($l);

        $a = $limiterOf(Client::withToken('https://example.com', 'tok'));
        $b = $limiterOf(Client::withToken('https://example.com', 'tok'));
        $this->assertNotNull($a);
        $this->assertNotSame($a, $b);
        $this->assertEqualsWithDelta(0.3, $interval($a), 1e-9);
        $this->assertEqualsWithDelta(0.15, $interval($limiterOf(Client::withToken('https://example.com', 'tok', 400))), 1e-9);
        $this->assertNull($limiterOf(Client::withToken('https://example.com', 'tok', 0)));
    }
}
