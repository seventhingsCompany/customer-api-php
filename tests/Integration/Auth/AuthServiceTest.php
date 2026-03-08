<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Auth;

use Seventhings\Client;
use Seventhings\Tests\Integration\IntegrationTestCase;

final class AuthServiceTest extends IntegrationTestCase
{
    public function testPing(): void
    {
        $baseUrl = getenv('SEVENTHINGS_BASE_URL');
        $client = Client::withToken($baseUrl, 'unused');
        $client->auth->ping();
        $this->assertTrue(true);
    }

    public function testLoginAndTokenPresence(): void
    {
        $baseUrl = getenv('SEVENTHINGS_BASE_URL');
        $username = getenv('SEVENTHINGS_USERNAME');
        $password = getenv('SEVENTHINGS_PASSWORD');
        $clientId = getenv('SEVENTHINGS_CLIENT_ID');

        $client = Client::withToken($baseUrl, 'unused');
        $token = $client->auth->login($username, $password, $clientId);

        $this->assertNotEmpty($token->accessToken);
        $this->assertNotEmpty($token->refreshToken);
        $this->assertGreaterThan(0, $token->expiresIn);
        $this->assertGreaterThan(0, $token->userId);
    }

    public function testRefreshAndRevoke(): void
    {
        $baseUrl = getenv('SEVENTHINGS_BASE_URL');
        $username = getenv('SEVENTHINGS_USERNAME');
        $password = getenv('SEVENTHINGS_PASSWORD');
        $clientId = getenv('SEVENTHINGS_CLIENT_ID');

        $client = Client::withToken($baseUrl, 'unused');
        $token = $client->auth->login($username, $password, $clientId);

        $refreshed = $client->auth->refresh($token->refreshToken);
        $this->assertNotEmpty($refreshed->accessToken);

        $client->setToken($refreshed->accessToken);
        $client->auth->revokeTokens();

        $this->assertTrue(true);
    }
}
