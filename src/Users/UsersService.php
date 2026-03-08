<?php

declare(strict_types=1);

namespace Seventhings\Users;

use Seventhings\HttpClient;
use Seventhings\Models\UserListOptions;
use Seventhings\Models\UserListResponse;
use Seventhings\Models\UserResponse;

final class UsersService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    public function list(?UserListOptions $options = null): UserListResponse
    {
        $path = 'users';
        if ($options !== null) {
            $qs = $options->toQueryString();
            if ($qs !== '') {
                $path .= '?' . $qs;
            }
        }

        $response = $this->httpClient->get($path);

        return UserListResponse::fromArray($response->json());
    }

    public function get(string $uuid): UserResponse
    {
        $response = $this->httpClient->get('user/' . $uuid);

        return UserResponse::fromArray($response->json());
    }

    public function getById(int $id): UserResponse
    {
        $response = $this->httpClient->get('user/by-id/' . $id);

        return UserResponse::fromArray($response->json());
    }
}
