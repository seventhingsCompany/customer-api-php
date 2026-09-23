<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Models;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\Models\ApiException;

final class ApiExceptionTest extends TestCase
{
    #[Test]
    public function messageFormat(): void
    {
        $e = new ApiException(404, 'Not Found', '{"error":"not found"}');
        $this->assertSame(
            'seventhings API error 404 (Not Found): {"error":"not found"}',
            $e->getMessage(),
        );
    }

    #[Test]
    public function exceptionCodeMatchesStatusCode(): void
    {
        $e = new ApiException(422, 'Unprocessable Entity', 'bad input');
        $this->assertSame(422, $e->getCode());
    }

    #[Test]
    public function isStatusCodeReturnsTrue(): void
    {
        $e = new ApiException(403, 'Forbidden', '');
        $this->assertTrue($e->isStatusCode(403));
    }

    #[Test]
    public function isStatusCodeReturnsFalse(): void
    {
        $e = new ApiException(403, 'Forbidden', '');
        $this->assertFalse($e->isStatusCode(404));
    }

    #[Test]
    public function propertiesAreAccessible(): void
    {
        $e = new ApiException(500, 'Internal Server Error', 'oops');
        $this->assertSame(500, $e->statusCode);
        $this->assertSame('Internal Server Error', $e->status);
        $this->assertSame('oops', $e->body);
    }

    #[Test]
    public function statusPredicates(): void
    {
        $this->assertTrue((new ApiException(404, 'Not Found', ''))->isNotFound());
        $this->assertTrue((new ApiException(401, 'Unauthorized', ''))->isUnauthorized());
        $this->assertTrue((new ApiException(403, 'Forbidden', ''))->isForbidden());
        $this->assertTrue((new ApiException(409, 'Conflict', ''))->isConflict());
        $this->assertTrue((new ApiException(429, 'Too Many Requests', ''))->isRateLimited());

        $this->assertTrue((new ApiException(500, 'Server Error', ''))->isServerError());
        $this->assertTrue((new ApiException(503, 'Unavailable', ''))->isServerError());
        $this->assertFalse((new ApiException(404, 'Not Found', ''))->isServerError());
        $this->assertFalse((new ApiException(200, 'OK', ''))->isNotFound());
    }

    public static function featureResponses(): iterable
    {
        $inactive = '{"message":"The required feature for this endpoint is not active","type":"FORBIDDEN","status":403,"code":403}';
        yield 'inactive feature' => [403, $inactive, true];
        yield 'permission denied' => [403, '{"message":"Permission denied"}', false];
        yield 'unauthorized' => [401, $inactive, false];
        yield 'not found' => [404, $inactive, false];
        yield 'server error' => [500, $inactive, false];
        yield 'missing message' => [403, '{"status":403}', false];
        yield 'nested message' => [403, '{"error":' . $inactive . '}', false];
        yield 'invalid json' => [403, '{', false];
        yield 'empty body' => [403, '', false];
        yield 'null body' => [403, 'null', false];
        yield 'scalar body' => [403, '42', false];
        yield 'string body' => [403, '"The required feature for this endpoint is not active"', false];
    }

    #[Test]
    #[DataProvider('featureResponses')]
    public function identifiesOnlyExplicitInactiveFeatureResponses(int $status, string $body, bool $expected): void
    {
        $error = new ApiException($status, 'Error', $body);
        $this->assertSame($expected, $error->isFeatureInactive());
    }
}
