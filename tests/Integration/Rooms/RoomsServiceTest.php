<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Rooms;

use Seventhings\Models\Enums\AssetTrackingTemplate;
use Seventhings\Tests\Integration\IntegrationTestCase;

final class RoomsServiceTest extends IntegrationTestCase
{
    /** @var string[] */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $uuid) {
            try {
                self::$client->rooms->delete($uuid);
            } catch (\Throwable) {
            }
        }
    }

    public function testRoomsCRUD(): void
    {
        // Discover mandatory fields for rooms
        $fields = self::$client->fieldDefinitions->list(AssetTrackingTemplate::Room);
        $roomFields = ['name' => 'PHP SDK Room ' . $this->uniqueSuffix()];
        foreach ($fields as $field) {
            $isMandatory = false;
            foreach ($field->attributes as $attr) {
                if ($attr->type === 'mandatory' && $attr->value === true) {
                    $isMandatory = true;
                    break;
                }
            }
            if ($isMandatory && $field->fieldKey !== 'name') {
                $roomFields[$field->fieldKey] = 'test-value';
            }
        }

        $uuid = self::$client->rooms->create($roomFields);
        $this->cleanup[] = $uuid;
        $this->assertNotEmpty($uuid);

        $room = self::$client->rooms->get($uuid);
        $this->assertSame($uuid, $room['uuid']);

        self::$client->rooms->patch($uuid, ['name' => 'PHP SDK Room Updated ' . $this->uniqueSuffix()]);

        $list = self::$client->rooms->list();
        $this->assertNotEmpty($list);

        $count = self::$client->rooms->count();
        $this->assertGreaterThan(0, $count);

        self::$client->rooms->delete($uuid);
        $this->cleanup = array_filter($this->cleanup, fn($id) => $id !== $uuid);
    }

    public function testRoomsCount(): void
    {
        $count = self::$client->rooms->count();
        $this->assertGreaterThanOrEqual(0, $count);
    }
}
