<?php

declare(strict_types=1);

namespace Seventhings\Rentals;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\CreateRentalCaseRequest;
use Seventhings\Models\ListOptions;
use Seventhings\Models\RentalCaseResponse;
use Seventhings\Models\UpdateRentalCaseRequest;

final class RentalsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    /**
     * @return RentalCaseResponse[]
     */
    public function list(?ListOptions $options = null): array
    {
        $response = $this->httpClient->get('rental-management/rental-cases', $options);

        return array_map(
            fn(array $item) => RentalCaseResponse::fromArray($item),
            $response->json()['items'],
        );
    }

    public function get(string $uuid): RentalCaseResponse
    {
        $response = $this->httpClient->get('rental-management/rental-case/' . $uuid);

        return RentalCaseResponse::fromArray($response->json());
    }

    public function create(CreateRentalCaseRequest $request): string
    {
        $response = $this->httpClient->post('rental-management/rental-case', $request->toArray());

        return Helpers::uuidFromLocationHeader($response);
    }

    public function update(string $uuid, UpdateRentalCaseRequest $request): void
    {
        $this->httpClient->put('rental-management/rental-case/' . $uuid, $request->toArray());
    }

    public function delete(string $uuid): void
    {
        $this->httpClient->delete('rental-management/rental-case/' . $uuid);
    }
}
