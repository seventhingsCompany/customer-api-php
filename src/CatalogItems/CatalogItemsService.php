<?php

declare(strict_types=1);

namespace Seventhings\CatalogItems;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\CatalogItem;
use Seventhings\Models\CatalogItemListResponse;
use Seventhings\Models\ListOptions;

/** Product catalog items. The instance must have the product catalog feature enabled. */
final class CatalogItemsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    /** Returns catalog items with pagination, filtering and sorting. */
    public function list(?ListOptions $options = null): CatalogItemListResponse
    {
        return CatalogItemListResponse::fromArray($this->httpClient->get('catalog-items', $options)->json());
    }

    /**
     * Iterates every catalog item across all pages.
     * See {@see \Seventhings\Objects\ObjectsService::all()} for paging semantics.
     *
     * @return \Generator<int, CatalogItem>
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
            ))->items;

            yield from $items;

            if (count($items) < $perPage) {
                return;
            }
        }
    }

    public function get(string $uuid): CatalogItem
    {
        return CatalogItem::fromArray($this->httpClient->get('catalog-item/' . rawurlencode($uuid))->json());
    }

    /**
     * Creates a catalog item and returns its UUID.
     *
     * @param array<string, mixed> $fields
     */
    public function create(array $fields): string
    {
        $response = $this->httpClient->post('catalog-item', ['fields' => (object) $fields]);

        return Helpers::uuidFromFileUpload($response);
    }

    /**
     * Replaces all catalog fields. Missing fields are emptied; objects
     * previously created from this item are not changed.
     *
     * @param array<string, mixed> $fields
     */
    public function update(string $uuid, array $fields): void
    {
        $this->httpClient->put('catalog-item/' . rawurlencode($uuid), ['fields' => (object) $fields]);
    }

    /** Deletes a catalog item, leaving objects created from it intact. */
    public function delete(string $uuid): void
    {
        $this->httpClient->delete('catalog-item/' . rawurlencode($uuid));
    }
}
