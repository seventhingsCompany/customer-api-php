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
}
