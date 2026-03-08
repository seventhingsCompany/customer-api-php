<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\Response;

final class ResponseTest extends TestCase
{
    #[Test]
    public function headerLineReturnsFirstValue(): void
    {
        $response = new Response(200, ['Content-Type' => ['application/json', 'text/html']], '');
        $this->assertSame('application/json', $response->headerLine('Content-Type'));
    }

    #[Test]
    public function headerLineIsCaseInsensitive(): void
    {
        $response = new Response(200, ['Content-Type' => ['application/json']], '');
        $this->assertSame('application/json', $response->headerLine('content-type'));
    }

    #[Test]
    public function headerLineReturnsEmptyStringForMissing(): void
    {
        $response = new Response(200, [], '');
        $this->assertSame('', $response->headerLine('X-Missing'));
    }

    #[Test]
    public function jsonDecodesBody(): void
    {
        $response = new Response(200, [], '{"key":"value","num":42}');
        $this->assertSame(['key' => 'value', 'num' => 42], $response->json());
    }

    #[Test]
    public function jsonThrowsOnInvalidBody(): void
    {
        $response = new Response(200, [], 'not json');
        $this->expectException(\JsonException::class);
        $response->json();
    }
}
