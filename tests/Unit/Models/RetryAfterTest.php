<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Models;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Models\ApiException;

final class RetryAfterTest extends TestCase
{
    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-16T12:00:00.250Z');
    }

    public static function values(): iterable
    {
        yield 'seconds' => [['120'], 120];
        yield 'zero' => [['0'], 0];
        yield 'leading zeros' => [['007'], 7];
        yield 'padded' => [[' 5 '], 5];
        yield 'max int' => [[(string) PHP_INT_MAX], PHP_INT_MAX];
        yield 'overflow' => [['99999999999999999999'], null];
        yield 'negative' => [['-1'], null];
        yield 'fraction' => [['1.5'], null];
        yield 'empty' => [[''], null];
        yield 'missing' => [[], null];
        yield 'repeated' => [['1', '2'], null];
        yield 'garbage' => [['soon'], null];
        yield 'imf date' => [['Fri, 16 Oct 2026 12:01:00 GMT'], 60];
        yield 'rfc850 date' => [['Friday, 16-Oct-26 12:01:00 GMT'], 60];
        yield 'asctime date' => [['Fri Oct 16 12:01:00 2026'], 60];
        yield 'date rounds up' => [['Fri, 16 Oct 2026 12:00:01 GMT'], 1];
        yield 'past date' => [['Fri, 16 Oct 2026 11:00:00 GMT'], 0];
        yield 'invalid date' => [['Fri, 32 Oct 2026 12:00:00 GMT'], null];
    }

    #[Test]
    #[DataProvider('values')]
    public function parsesRetryAfter(array $values, ?int $expected): void
    {
        $headers = $values === [] ? [] : ['retry-after' => $values];
        $e = new ApiException(429, 'Too Many Requests', '', null, $headers);
        $this->assertSame($expected, $e->retryAfter(self::now()));
    }

    #[Test]
    public function headersAreCapturedButNotInMessage(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(503, ['Retry-After' => '30', 'X-Request-Id' => 'abc'], 'down'),
        ]));
        $http = new HttpClient('https://example.com', new GuzzleClient(['handler' => $stack]));

        try {
            $http->get('objects');
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(30, $e->retryAfter());
            $this->assertSame(['abc'], $e->header('x-request-id'));
            $this->assertSame('seventhings API error 503 (Service Unavailable): down', $e->getMessage());
        }
    }
}
