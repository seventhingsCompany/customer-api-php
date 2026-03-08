<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\FieldDefinitions;

use Seventhings\Models\CreateFieldDefinition;
use Seventhings\Models\Enums\AssetTrackingTemplate;
use Seventhings\Models\Enums\FieldTypeName;
use Seventhings\Models\FieldDefinitionFieldType;
use Seventhings\Models\UpdateFieldDefinition;
use Seventhings\Tests\Integration\IntegrationTestCase;

final class FieldDefinitionsServiceTest extends IntegrationTestCase
{
    public function testFieldDefinitionsList(): void
    {
        $definitions = self::$client->fieldDefinitions->list(AssetTrackingTemplate::Asset);
        $this->assertIsArray($definitions);
        $this->assertNotEmpty($definitions);
    }

    public function testFieldDefinitionsCRUD(): void
    {
        $label = 'PHP SDK Field ' . $this->uniqueSuffix();
        $fieldType = new FieldDefinitionFieldType(FieldTypeName::Text, []);

        $input = new CreateFieldDefinition(
            fieldType: $fieldType,
            label: $label,
            attributes: [],
            relations: [],
        );

        $uuid = self::$client->fieldDefinitions->create(AssetTrackingTemplate::Asset, $input);
        $this->assertNotEmpty($uuid);

        $field = self::$client->fieldDefinitions->get(AssetTrackingTemplate::Asset, $uuid);
        $this->assertSame($uuid, $field->uuid);
        $this->assertSame($label, $field->label);

        $updatedLabel = $label . ' Updated';
        $updateInput = new UpdateFieldDefinition(
            uuid: $field->uuid,
            fieldKey: $field->fieldKey,
            fieldType: $fieldType,
            label: $updatedLabel,
            attributes: [],
            relations: [],
        );

        self::$client->fieldDefinitions->update(AssetTrackingTemplate::Asset, $uuid, $updateInput);

        $updated = self::$client->fieldDefinitions->get(AssetTrackingTemplate::Asset, $uuid);
        $this->assertSame($updatedLabel, $updated->label);
    }
}
