<?php

declare(strict_types=1);

namespace Seventhings\Persons;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\FilterObject;
use Seventhings\Models\PersonListOptions;
use Seventhings\Models\PersonListResponse;
use Seventhings\Models\PersonResponse;

final class PersonsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    public function list(?PersonListOptions $options = null): PersonListResponse
    {
        $path = 'persons';
        if ($options !== null) {
            $qs = $options->toQueryString();
            if ($qs !== '') {
                $path .= '?' . $qs;
            }
        }

        $response = $this->httpClient->get($path);

        return PersonListResponse::fromArray($response->json());
    }

    public function get(string $uuid): PersonResponse
    {
        $response = $this->httpClient->get('person/' . $uuid);

        return PersonResponse::fromArray($response->json());
    }

    public function getById(int $id): PersonResponse
    {
        $response = $this->httpClient->get('person/by-id/' . $id);

        return PersonResponse::fromArray($response->json());
    }

    /**
     * Creates a new person and returns the UUID parsed from the
     * Location header of the 201 response.
     *
     * @param array<string, mixed> $fields
     */
    public function create(array $fields): string
    {
        $response = $this->httpClient->post('persons', ['fields' => (object) $fields]);

        return Helpers::uuidFromLocationHeader($response);
    }

    /**
     * Triggers user creation for the person(s) matched by the given
     * filter. Only the filter field is sent in the request body.
     */
    public function createUser(FilterObject $filter): void
    {
        $this->httpClient->post('persons/create-user', [
            'filter' => (object) $filter->filter,
        ]);
    }
}
