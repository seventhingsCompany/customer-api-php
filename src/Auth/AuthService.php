<?php

declare(strict_types=1);

namespace Seventhings\Auth;

use Seventhings\HttpClient;
use Seventhings\Models\Enums\SSOAppTarget;
use Seventhings\Models\Enums\SSOProviderName;
use Seventhings\Models\TokenResponse;

final class AuthService
{
    private string $clientId = '';

    public function __construct(private readonly HttpClient $httpClient) {}

    public function login(string $username, string $password, string $clientId): TokenResponse
    {
        $this->clientId = $clientId;

        $response = $this->httpClient->postUnauthenticated('auth_token', [
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
            'client_id' => $clientId,
        ]);

        return TokenResponse::fromArray($response->json());
    }

    /**
     * Exchanges an OAuth 2.1 authorization code using PKCE. $redirectUri must
     * match the URI used for authorization; $codeVerifier is the original
     * verifier used to generate the authorization request's challenge.
     */
    public function loginAuthorizationCode(string $code, string $codeVerifier, string $redirectUri, string $clientId): TokenResponse
    {
        $this->clientId = $clientId;

        $response = $this->httpClient->postFormUnauthenticated('auth_token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
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
        $this->clientId = $clientId;

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
            'client_id' => $this->clientId,
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
