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

    /**
     * Iterates every person across all pages, fetching one page at a time.
     * The page from $options is ignored (iteration controls it); its perPage
     * sets the page size and defaults to 100. Iteration stops on the first
     * short (or empty) page.
     *
     * @return \Generator<int, PersonResponse>
     */
    public function all(?PersonListOptions $options = null): \Generator
    {
        $perPage = $options?->perPage ?? Helpers::DEFAULT_PAGE_SIZE;

        for ($page = 1; ; $page++) {
            $items = $this->list(new PersonListOptions(
                page: $page,
                perPage: $perPage,
                sort: $options?->sort ?? [],
            ))->items;

            yield from $items;

            if (count($items) < $perPage) {
                return;
            }
        }
    }

    public function count(?PersonListOptions $options = null): int
    {
        $path = 'persons/count';
        if ($options !== null) {
            $qs = $options->toQueryString();
            if ($qs !== '') {
                $path .= '?' . $qs;
            }
        }

        return $this->httpClient->get($path)->json()['count'];
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
        $response = $this->httpClient->post('person', ['fields' => (object) $fields]);

        return Helpers::uuidFromLocationHeader($response);
    }

    /**
     * Updates a person's fields. Unlike create, the PATCH endpoint expects
     * the fields map directly (not wrapped in a `fields` object), and the
     * API responds with an empty body — fetch the person again to read the
     * updated values.
     *
     * @param array<string, mixed> $fields
     */
    public function patch(string $uuid, array $fields): void
    {
        $this->httpClient->patch('person/' . $uuid, $fields);
    }

    public function delete(string $uuid): void
    {
        $this->httpClient->delete('person/' . $uuid);
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
