<?php

declare(strict_types=1);

namespace Seventhings\Users;

use Seventhings\Helpers;
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

    /**
     * Iterates every user across all pages, fetching one page at a time. The
     * page from $options is ignored (iteration controls it); its perPage sets
     * the page size and defaults to 100. Iteration stops on the first short
     * (or empty) page.
     *
     * @return \Generator<int, UserResponse>
     */
    public function all(?UserListOptions $options = null): \Generator
    {
        $perPage = $options?->perPage ?? Helpers::DEFAULT_PAGE_SIZE;

        for ($page = 1; ; $page++) {
            $items = $this->list(new UserListOptions(
                page: $page,
                perPage: $perPage,
                sortBy: $options?->sortBy,
                order: $options?->order,
            ))->items;

            yield from $items;

            if (count($items) < $perPage) {
                return;
            }
        }
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
