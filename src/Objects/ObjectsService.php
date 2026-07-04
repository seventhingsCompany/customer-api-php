<?php

declare(strict_types=1);

namespace Seventhings\Objects;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\Fields;
use Seventhings\Models\ListOptions;
use Seventhings\Response;

final class ObjectsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    public function list(?ListOptions $options = null): array
    {
        return $this->httpClient->get('objects', $options)->json()['items'];
    }

    /**
     * Iterates every object across all pages as type-safe {@see Fields}
     * wrappers, fetching one page at a time. The page from $options is ignored
     * (iteration controls it); its perPage sets the page size and defaults to
     * 100. Iteration stops on the first short (or empty) page.
     *
     * @return \Generator<int, Fields>
     */
    public function all(?ListOptions $options = null): \Generator
    {
        $perPage = $options?->perPage ?? Helpers::DEFAULT_PAGE_SIZE;

        for ($page = 1; ; $page++) {
            $items = $this->list(new ListOptions(
                page: $page,
                perPage: $perPage,
                sort: $options?->sort ?? [],
                filters: $options?->filters ?? [],
            ));

            foreach ($items as $item) {
                yield new Fields($item);
            }

            if (count($items) < $perPage) {
                return;
            }
        }
    }

    public function count(?ListOptions $options = null): int
    {
        return $this->httpClient->get('objects/count', $options)->json()['count'];
    }

    public function create(array $fields): string
    {
        $response = $this->httpClient->post('object', $fields);

        return Helpers::uuidFromLocationHeader($response);
    }

    public function get(string $uuid): array
    {
        return $this->httpClient->get('object/' . $uuid)->json();
    }

    public function patch(string $uuid, array $fields): void
    {
        $this->httpClient->patch('object/' . $uuid, $fields);
    }

    public function delete(string $uuid): void
    {
        $this->httpClient->delete('object/' . $uuid);
    }

    public function archive(string $uuid): void
    {
        $this->httpClient->post('object/' . $uuid . '/archive');
    }

    public function unarchive(string $uuid): void
    {
        $this->httpClient->post('object/' . $uuid . '/unarchive');
    }

    /**
     * @param \Seventhings\Models\FileAttachment[] $attachments
     */
    public function addFiles(string $uuid, array $attachments): Response
    {
        return $this->httpClient->post(
            'object/' . $uuid . '/add-file',
            array_map(fn($a) => $a->toArray(), $attachments),
        );
    }

    /**
     * @param \Seventhings\Models\FileAttachment[] $attachments
     */
    public function removeFiles(string $uuid, array $attachments): Response
    {
        return $this->httpClient->post(
            'object/' . $uuid . '/remove-file',
            array_map(fn($a) => $a->toArray(), $attachments),
        );
    }
}
