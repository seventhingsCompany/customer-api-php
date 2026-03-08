<?php

declare(strict_types=1);

namespace Seventhings\Locations;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\ListOptions;

final class LocationsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    public function list(?ListOptions $options = null): array
    {
        return $this->httpClient->get('locations', $options)->json();
    }

    public function count(?ListOptions $options = null): int
    {
        return $this->httpClient->get('locations/count', $options)->json()['count'];
    }

    public function create(array $fields): string
    {
        $response = $this->httpClient->post('location', $fields);

        return Helpers::uuidFromLocationHeader($response);
    }

    public function get(string $uuid): array
    {
        return $this->httpClient->get('location/' . $uuid)->json();
    }

    public function patch(string $uuid, array $fields): array
    {
        return $this->httpClient->patch('location/' . $uuid, $fields)->json();
    }

    public function delete(string $uuid): void
    {
        $this->httpClient->delete('location/' . $uuid);
    }
}
