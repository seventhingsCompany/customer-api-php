<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Models;

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
}
