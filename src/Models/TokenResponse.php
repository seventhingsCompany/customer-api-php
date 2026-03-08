<?php

declare(strict_types=1);

namespace Seventhings\Models;

readonly class TokenResponse
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public string $tokenType,
        public ?string $scope,
        public string $refreshToken,
        public int $userId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            expiresIn: $data['expires_in'],
            tokenType: $data['token_type'],
            scope: $data['scope'] ?? null,
            refreshToken: $data['refresh_token'],
            userId: $data['user_id'],
        );
    }
}
