<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\SkippedWithMessageException;
use PHPUnit\Framework\TestCase;
use Seventhings\Client;
use Seventhings\HttpClient;
use Seventhings\Models\ApiException;
use Seventhings\Models\NetworkException;
use Seventhings\Tests\Integration\IntegrationTestCase;
use Seventhings\Tests\Integration\NewEndpointsTest;

final class OptionalRentalsTest extends TestCase
{
    private const INACTIVE_BODY = '{"message":"The required feature for this endpoint is not active","type":"FORBIDDEN","status":403,"code":403}';

    private function runHistoryTest(array $responses, string $module = 'rentals'): void
    {
        $http = new HttpClient('https://example.com', new GuzzleClient([
            'handler' => HandlerStack::create(new MockHandler($responses)),
        ]));
        $reflection = new \ReflectionClass(Client::class);
        $client = $reflection->newInstanceWithoutConstructor();
        $reflection->getConstructor()->invoke($client, $http);

        // Exercise the actual integration-test skip path without live credentials.
        $property = new \ReflectionProperty(IntegrationTestCase::class, 'client');
        $previous = $property->getValue();
        $property->setValue(null, $client);
        try {
            $test = new NewEndpointsTest('historyPaginationMatchesCombinedPage');
            $test->historyPaginationMatchesCombinedPage($module, $module === 'rooms' ? 'room_uuid' : null);
        } finally {
            $property->setValue(null, $previous);
        }
    }

    #[Test]
    public function inactiveRentalsAreSkipped(): void
    {
        try {
            $this->runHistoryTest([new GuzzleResponse(403, [], self::INACTIVE_BODY)]);
            $this->fail('Expected the inactive rentals case to be skipped');
        } catch (SkippedWithMessageException $e) {
            $this->assertSame('Rentals module is not active on this instance', $e->getMessage());
        }
    }

    public static function unexpectedErrors(): iterable
    {
        yield 'permission denied' => [403, '{"message":"Permission denied"}', 'rentals'];
        yield 'unauthorized' => [401, self::INACTIVE_BODY, 'rentals'];
        yield 'not found' => [404, self::INACTIVE_BODY, 'rentals'];
        yield 'server error' => [500, self::INACTIVE_BODY, 'rentals'];
        yield 'other resource' => [403, self::INACTIVE_BODY, 'rooms'];
    }

    #[Test]
    #[DataProvider('unexpectedErrors')]
    public function unexpectedApiErrorsAreNotSkipped(int $status, string $body, string $module): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode($status);
        $this->runHistoryTest([new GuzzleResponse($status, [], $body)], $module);
    }

    #[Test]
    public function networkFailuresAreNotSkipped(): void
    {
        $this->expectException(NetworkException::class);
        $this->runHistoryTest([new ConnectException('Connection failed', new Request('GET', 'https://example.com'))]);
    }

    #[Test]
    public function invalidResponsesAreNotSkipped(): void
    {
        $this->expectException(\JsonException::class);
        $this->runHistoryTest([new GuzzleResponse(200, [], '{')]);
    }

    #[Test]
    public function historyFailuresAfterSuccessfulListingAreNotSkipped(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(403);
        $this->runHistoryTest([
            new GuzzleResponse(200, [], '{"items":[{"uuid":"rental-1","status":"borrowed","title":"Test rental"}]}'),
            new GuzzleResponse(403, [], '{"message":"Permission denied"}'),
        ]);
    }
}
