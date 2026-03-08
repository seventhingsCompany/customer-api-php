<?php

declare(strict_types=1);

namespace Seventhings\FieldDefinitions;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\CreateFieldDefinition;
use Seventhings\Models\Enums\AssetTrackingTemplate;
use Seventhings\Models\FieldDefinition;
use Seventhings\Models\UpdateFieldDefinition;

final class FieldDefinitionsService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    /**
     * @return FieldDefinition[]
     */
    public function list(AssetTrackingTemplate $template): array
    {
        $response = $this->httpClient->get('asset-tracking/' . $template->value . '/field-definitions');

        return array_map(
            fn(array $item) => FieldDefinition::fromArray($item),
            $response->json(),
        );
    }

    public function get(AssetTrackingTemplate $template, string $uuid): FieldDefinition
    {
        $response = $this->httpClient->get('asset-tracking/' . $template->value . '/field-definition/' . $uuid);

        return FieldDefinition::fromArray($response->json());
    }

    public function create(AssetTrackingTemplate $template, CreateFieldDefinition $input): string
    {
        $response = $this->httpClient->post('asset-tracking/' . $template->value . '/field-definition', $input->toArray());

        return Helpers::uuidFromLocationHeader($response);
    }

    public function update(AssetTrackingTemplate $template, string $uuid, UpdateFieldDefinition $input): void
    {
        $this->httpClient->put('asset-tracking/' . $template->value . '/field-definition/' . $uuid, $input->toArray());
    }
}
