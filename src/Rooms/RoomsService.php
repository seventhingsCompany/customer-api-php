<?php

declare(strict_types=1);

namespace Seventhings\Rooms;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\Fields;
use Seventhings\Models\HistoryListOptions;
use Seventhings\Models\HistoryResponse;
use Seventhings\Models\ListOptions;
use Seventhings\Models\RoomHistoryEntry;

final class RoomsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    public function list(?ListOptions $options = null): array
    {
        return array_map(
            Helpers::unwrapResourceFields(...),
            $this->httpClient->get('rooms', $options)->json()['items'],
        );
    }

    /**
     * Iterates every room across all pages as type-safe {@see Fields} wrappers.
     * See {@see \Seventhings\Objects\ObjectsService::all()} for paging semantics.
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
        return $this->httpClient->get('rooms/count', $options)->json()['count'];
    }

    public function create(array $fields): string
    {
        $response = $this->httpClient->post('room', $fields);

        return Helpers::uuidFromLocationHeader($response);
    }

    public function get(string $uuid): array
    {
        return Helpers::unwrapResourceFields($this->httpClient->get('room/' . $uuid)->json());
    }

    /** @return HistoryResponse<RoomHistoryEntry> Recorded changes, newest first. */
    public function history(string $uuid, ?HistoryListOptions $options = null): HistoryResponse
    {
        return HistoryResponse::fromArray(
            $this->httpClient->get('room/' . rawurlencode($uuid) . '/history', $options)->json(),
            RoomHistoryEntry::fromArray(...),
        );
    }

    public function patch(string $uuid, array $fields): array
    {
        return Helpers::unwrapResourceFields($this->httpClient->patch('room/' . $uuid, $fields)->json());
    }

    public function delete(string $uuid): void
    {
        $this->httpClient->delete('room/' . $uuid);
    }
}
