<?php

declare(strict_types=1);

namespace Seventhings\CircularityHub;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\AddObjectEntry;
use Seventhings\Models\CircularityHubOrder;
use Seventhings\Models\FilterObject;
use Seventhings\Models\ListOptions;

final class CircularityHubService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    /**
     * @return array<string, string>|null
     */
    public function suggestCategory(FilterObject $filter): ?array
    {
        $response = $this->httpClient->post('circularity-hub/suggest-category', $filter->toArray());
        $data = $response->json();

        if ($data === []) {
            return null;
        }

        return $data;
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>|null
     */
    public function suggestRestPrice(array $input): ?array
    {
        $response = $this->httpClient->post('circularity-hub/suggest-rest-price', $input);
        $data = $response->json();

        if ($data === []) {
            return null;
        }

        return $data;
    }

    /**
     * @param array<string, AddObjectEntry> $entries
     */
    public function addObjects(array $entries): void
    {
        $body = [];
        foreach ($entries as $key => $entry) {
            $body[$key] = $entry->toArray();
        }

        $this->httpClient->post('circularity-hub/add-objects-to-circularity-hub', $body);
    }

    public function listItems(?ListOptions $options = null): array
    {
        $response = $this->httpClient->get('circularity-hub/items', $options);

        return $response->json()['items'];
    }

    public function getItem(int $id): array
    {
        $response = $this->httpClient->get('circularity-hub/item/' . $id);

        return $response->json();
    }

    public function updateItem(int $id, array $data): void
    {
        $this->httpClient->patch('circularity-hub/item/' . $id, $data);
    }

    public function deleteItem(int $id): void
    {
        $this->httpClient->delete('circularity-hub/item/' . $id);
    }

    /**
     * @return CircularityHubOrder[]
     */
    public function listOrders(?ListOptions $options = null): array
    {
        $response = $this->httpClient->get('circularity-hub/orders', $options);

        return array_map(
            fn(array $item) => CircularityHubOrder::fromArray($item),
            $response->json()['items'],
        );
    }

    /**
     * @param int[] $itemIds
     */
    public function createOrder(array $itemIds): int
    {
        $response = $this->httpClient->post('circularity-hub/orders', $itemIds);

        return Helpers::intFromLocationIdHeader($response);
    }

    public function getOrder(int $id): CircularityHubOrder
    {
        $response = $this->httpClient->get('circularity-hub/order/' . $id);

        return CircularityHubOrder::fromArray($response->json());
    }

    public function updateOrder(int $id, array $data): void
    {
        $this->httpClient->patch('circularity-hub/order/' . $id, $data);
    }
}
