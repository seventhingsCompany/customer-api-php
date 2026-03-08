<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Seventhings\Client;

#[Group('integration')]
abstract class IntegrationTestCase extends TestCase
{
    protected static ?Client $client = null;

    protected function setUp(): void
    {
        $baseUrl = getenv('SEVENTHINGS_BASE_URL');
        $username = getenv('SEVENTHINGS_USERNAME');
        $password = getenv('SEVENTHINGS_PASSWORD');
        $clientId = getenv('SEVENTHINGS_CLIENT_ID');

        if (!$baseUrl || !$username || !$password || !$clientId) {
            $this->markTestSkipped('Set SEVENTHINGS_BASE_URL, SEVENTHINGS_USERNAME, SEVENTHINGS_PASSWORD, SEVENTHINGS_CLIENT_ID to run integration tests');
        }

        if (self::$client === null) {
            self::$client = Client::withCredentials($baseUrl, $username, $password, $clientId);
        }
    }

    protected function uniqueSuffix(): string
    {
        return (string) intval(microtime(true) * 1000);
    }
}
