<?php

declare(strict_types=1);

namespace Seventhings\Rooms;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\ListOptions;

final class RoomsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    public function list(?ListOptions $options = null): array
    {
        return $this->httpClient->get('rooms', $options)->json()['items'];
    }

    public function count(?ListOptions $options = null): int
    {
        return $this->httpClient->get('rooms/count', $options)->json()['count'];
    }

    public function create(array $fields): string
    {
        $response = $this->httpClient->post('room', $fields);

        return Helpers::uuidFromLocationHeader($response);
    }

    public function get(string $uuid): array
    {
        return $this->httpClient->get('room/' . $uuid)->json();
    }

    public function patch(string $uuid, array $fields): array
    {
        return $this->httpClient->patch('room/' . $uuid, $fields)->json();
    }

    public function delete(string $uuid): void
    {
        $this->httpClient->delete('room/' . $uuid);
    }
}
