<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\FieldDefinitions;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\FieldDefinitions\FieldDefinitionsService;
use Seventhings\HttpClient;
use Seventhings\Models\CreateFieldDefinition;
use Seventhings\Models\Enums\AssetTrackingTemplate;
use Seventhings\Models\Enums\FieldTypeName;
use Seventhings\Models\FieldAttribute;
use Seventhings\Models\FieldDefinition;
use Seventhings\Models\FieldDefinitionFieldType;
use Seventhings\Models\FieldRelation;
use Seventhings\Models\FieldValueConstraint;
use Seventhings\Models\UpdateFieldDefinition;

final class FieldDefinitionsServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): FieldDefinitionsService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new FieldDefinitionsService($httpClient);
    }

    private function sampleFieldDefinitionData(): array
    {
        return [
            'uuid' => 'fd-1',
            'field_key' => 'custom_field_1',
            'field_type' => [
                'name' => 'TEXT',
                'constraints' => [
                    ['type' => 'max_length', 'value' => 255],
                ],
            ],
            'label' => 'Custom Field',
            'attributes' => [
                ['type' => 'required', 'value' => true],
            ],
            'relations' => [
                ['type' => 'depends_on', 'field_uuid' => 'fd-2', 'comparison_values' => ['val1', 'val2']],
            ],
            'comment' => 'A custom field',
            'default_value' => 'hello',
            'possible_values' => ['a', 'b', 'c'],
        ];
    }

    #[Test]
    public function listReturnsFieldDefinitionArray(): void
    {
        $data = [$this->sampleFieldDefinitionData()];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->list(AssetTrackingTemplate::Asset);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(FieldDefinition::class, $result[0]);
        $this->assertSame('fd-1', $result[0]->uuid);
        $this->assertSame('custom_field_1', $result[0]->fieldKey);
        $this->assertSame(FieldTypeName::Text, $result[0]->fieldType->name);
        $this->assertCount(1, $result[0]->fieldType->constraints);
        $this->assertSame('max_length', $result[0]->fieldType->constraints[0]->type);
        $this->assertSame(255, $result[0]->fieldType->constraints[0]->value);
        $this->assertSame('Custom Field', $result[0]->label);
        $this->assertCount(1, $result[0]->attributes);
        $this->assertSame('required', $result[0]->attributes[0]->type);
        $this->assertTrue($result[0]->attributes[0]->value);
        $this->assertCount(1, $result[0]->relations);
        $this->assertSame('depends_on', $result[0]->relations[0]->type);
        $this->assertSame('fd-2', $result[0]->relations[0]->fieldUUID);
        $this->assertSame(['val1', 'val2'], $result[0]->relations[0]->comparisonValues);
        $this->assertSame('A custom field', $result[0]->comment);
        $this->assertSame('hello', $result[0]->defaultValue);
        $this->assertSame(['a', 'b', 'c'], $result[0]->possibleValues);

        $this->assertStringEndsWith('/asset-tracking/asset/field-definitions', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function listUsesRoomTemplate(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], json_encode([]))]);

        $service->list(AssetTrackingTemplate::Room);

        $this->assertStringEndsWith('/asset-tracking/room/field-definitions', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function getReturnsFieldDefinition(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($this->sampleFieldDefinitionData()))]);

        $result = $service->get(AssetTrackingTemplate::Asset, 'fd-1');

        $this->assertInstanceOf(FieldDefinition::class, $result);
        $this->assertSame('fd-1', $result->uuid);
        $this->assertSame('Custom Field', $result->label);
        $this->assertStringEndsWith('/asset-tracking/asset/field-definition/fd-1', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function getNullableFields(): void
    {
        $data = $this->sampleFieldDefinitionData();
        $data['comment'] = null;
        $data['default_value'] = null;
        $data['possible_values'] = null;

        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->get(AssetTrackingTemplate::Asset, 'fd-1');

        $this->assertNull($result->comment);
        $this->assertNull($result->defaultValue);
        $this->assertNull($result->possibleValues);
    }

    #[Test]
    public function createReturnsUuid(): void
    {
        $service = $this->createService([
            new GuzzleResponse(201, ['Location' => '/customer-api/v1/asset-tracking/asset/field-definition/new-uuid'], ''),
        ]);

        $input = new CreateFieldDefinition(
            fieldType: new FieldDefinitionFieldType(FieldTypeName::Text),
            label: 'New Field',
            attributes: [new FieldAttribute('required', true)],
            relations: [new FieldRelation('depends_on', 'fd-2', ['x'])],
            comment: 'A comment',
            defaultValue: 'default',
            possibleValues: ['a', 'b'],
        );

        $uuid = $service->create(AssetTrackingTemplate::Asset, $input);

        $this->assertSame('new-uuid', $uuid);

        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringEndsWith('/asset-tracking/asset/field-definition', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('TEXT', $body['field_type']['name']);
        $this->assertSame('New Field', $body['label']);
        $this->assertSame([['type' => 'required', 'value' => true]], $body['attributes']);
        $this->assertSame([['type' => 'depends_on', 'field_uuid' => 'fd-2', 'comparison_values' => ['x']]], $body['relations']);
        $this->assertSame('A comment', $body['comment']);
        $this->assertSame('default', $body['default_value']);
        $this->assertSame(['a', 'b'], $body['possible_values']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function mandatoryFieldData(string $fieldKey, array $overrides = []): array
    {
        return array_merge($this->sampleFieldDefinitionData(), [
            'field_key' => $fieldKey,
            'attributes' => [['type' => 'mandatory', 'value' => 'yes']],
        ], $overrides);
    }

    #[Test]
    public function mandatoryFieldDefinitionsFiltersRequiredAndSystemManaged(): void
    {
        $data = [
            $this->mandatoryFieldData('cost_center'),                              // required, custom → kept
            $this->mandatoryFieldData('id'),                                       // required but system-managed → dropped
            array_merge($this->sampleFieldDefinitionData(), [                      // not mandatory → dropped
                'field_key' => 'nickname',
                'attributes' => [['type' => 'mandatory', 'value' => 'no']],
            ]),
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->mandatoryFieldDefinitions(AssetTrackingTemplate::Person);

        $this->assertCount(1, $result);
        $this->assertSame('cost_center', $result[0]->fieldKey);
        $this->assertTrue($result[0]->isMandatory());
    }

    #[Test]
    public function missingMandatoryFieldsReportsAbsentAndNullKeys(): void
    {
        $data = [
            $this->mandatoryFieldData('cost_center'),
            $this->mandatoryFieldData('department'),
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $missing = $service->missingMandatoryFields(
            AssetTrackingTemplate::Person,
            ['cost_center' => 'CC-1', 'department' => null],
        );

        // cost_center present → satisfied; department null → still missing.
        $this->assertSame(['department'], $missing);
    }

    #[Test]
    public function missingMandatoryFieldsEmptyWhenSatisfied(): void
    {
        $data = [$this->mandatoryFieldData('cost_center')];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $missing = $service->missingMandatoryFields(
            AssetTrackingTemplate::Person,
            ['cost_center' => 'CC-1'],
        );

        $this->assertSame([], $missing);
    }

    #[Test]
    public function allowedValuesReadsConstraint(): void
    {
        $data = $this->sampleFieldDefinitionData();
        $data['field_type'] = [
            'name' => 'DROPDOWN',
            'constraints' => [
                ['type' => 'allowed_values', 'value' => ['red', 'green', 'blue']],
            ],
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $def = $service->get(AssetTrackingTemplate::Asset, 'fd-1');

        $this->assertSame(['red', 'green', 'blue'], $def->fieldType->allowedValues());
    }

    #[Test]
    public function allowedValuesNullWhenAbsent(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($this->sampleFieldDefinitionData()))]);

        $def = $service->get(AssetTrackingTemplate::Asset, 'fd-1');

        $this->assertNull($def->fieldType->allowedValues());
    }

    #[Test]
    public function updateSendsPut(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $input = new UpdateFieldDefinition(
            uuid: 'fd-1',
            fieldKey: 'custom_field_1',
            fieldType: new FieldDefinitionFieldType(FieldTypeName::Number),
            label: 'Updated Field',
        );

        $service->update(AssetTrackingTemplate::Asset, 'fd-1', $input);

        $req = $this->history[0]['request'];
        $this->assertSame('PUT', $req->getMethod());
        $this->assertStringEndsWith('/asset-tracking/asset/field-definition/fd-1', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('fd-1', $body['uuid']);
        $this->assertSame('custom_field_1', $body['field_key']);
        $this->assertSame('NUMBER', $body['field_type']['name']);
        $this->assertSame('Updated Field', $body['label']);
    }
}
