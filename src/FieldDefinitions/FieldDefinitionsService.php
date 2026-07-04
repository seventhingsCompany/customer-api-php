<?php

declare(strict_types=1);

namespace Seventhings\FieldDefinitions;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\CreateFieldDefinition;
use Seventhings\Models\Enums\AssetTrackingTemplate;
use Seventhings\Models\FieldDefinition;
use Seventhings\Models\SystemManagedFieldKeys;
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

    /**
     * Returns the field definitions for the given template that are configured
     * as required for this instance. Use it before creating a resource
     * (object/asset, room, person) to discover which fields must be supplied —
     * this can vary per instance.
     *
     * System-managed keys (see {@see SystemManagedFieldKeys}) are excluded,
     * since they may be reported as mandatory but must not be sent on create.
     *
     * @return FieldDefinition[]
     */
    public function mandatoryFieldDefinitions(AssetTrackingTemplate $template): array
    {
        return array_values(array_filter(
            $this->list($template),
            fn(FieldDefinition $d) => $d->isMandatory() && !SystemManagedFieldKeys::contains($d->fieldKey),
        ));
    }

    /**
     * Returns the field keys required for the given template (per this
     * instance) that are absent — or present with a null value — in $fields.
     * System-managed keys are excluded, so they never appear in the result.
     * An empty array means the payload satisfies every instance-required field.
     *
     * Use it to fail fast before create. It performs one request (to fetch
     * field definitions) and checks presence only — it does not validate values.
     *
     * @param array<string, mixed> $fields
     * @return list<string>
     */
    public function missingMandatoryFields(AssetTrackingTemplate $template, array $fields): array
    {
        $missing = [];
        foreach ($this->mandatoryFieldDefinitions($template) as $definition) {
            if (($fields[$definition->fieldKey] ?? null) === null) {
                $missing[] = $definition->fieldKey;
            }
        }
        return $missing;
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
