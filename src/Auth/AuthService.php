<?php

declare(strict_types=1);

namespace Seventhings\Auth;

use Seventhings\HttpClient;
use Seventhings\Models\Enums\SSOAppTarget;
use Seventhings\Models\Enums\SSOProviderName;
use Seventhings\Models\TokenResponse;

final class AuthService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    public function login(string $username, string $password, string $clientId): TokenResponse
    {
        $response = $this->httpClient->postUnauthenticated('auth_token', [
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
            'client_id' => $clientId,
        ]);

        return TokenResponse::fromArray($response->json());
    }

    public function loginSSO(
        SSOProviderName $provider,
        string $authCode,
        string $clientId,
        ?SSOAppTarget $appTarget = null,
    ): TokenResponse {
        $body = [
            'grant_type' => 'sso',
            'provider' => $provider->value,
            'auth_code' => $authCode,
            'client_id' => $clientId,
        ];

        if ($appTarget !== null) {
            $body['app_target'] = $appTarget->value;
        }

        $response = $this->httpClient->postUnauthenticated('auth_token', $body);

        return TokenResponse::fromArray($response->json());
    }

    public function refresh(string $refreshToken): TokenResponse
    {
        $response = $this->httpClient->postUnauthenticated('auth_token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        return TokenResponse::fromArray($response->json());
    }

    public function revokeTokens(): void
    {
        $this->httpClient->delete('auth_token');
    }

    public function ping(): void
    {
        $this->httpClient->getUnauthenticated('');
    }
}
