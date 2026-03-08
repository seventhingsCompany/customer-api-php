<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Objects;

use Seventhings\Models\FileAttachment;
use Seventhings\Models\FilterEntry;
use Seventhings\Models\Enums\FilterOperator;
use Seventhings\Models\ListOptions;
use Seventhings\Tests\Integration\IntegrationTestCase;

final class ObjectsServiceTest extends IntegrationTestCase
{
    /** @var string[] */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $uuid) {
            try {
                self::$client->objects->delete($uuid);
            } catch (\Throwable) {
            }
        }
    }

    public function testObjectsCRUD(): void
    {
        $name = 'PHP SDK Test ' . $this->uniqueSuffix();
        $uuid = self::$client->objects->create(['name' => $name]);
        $this->cleanup[] = $uuid;
        $this->assertNotEmpty($uuid);

        $object = self::$client->objects->get($uuid);
        $this->assertSame($uuid, $object['uuid']);

        self::$client->objects->patch($uuid, ['name' => $name . ' Updated']);

        $list = self::$client->objects->list();
        $this->assertNotEmpty($list);

        self::$client->objects->delete($uuid);
        $this->cleanup = array_filter($this->cleanup, fn($id) => $id !== $uuid);
    }

    public function testObjectsListWithFilters(): void
    {
        $name = 'PHP SDK Filter ' . $this->uniqueSuffix();
        $uuid = self::$client->objects->create(['name' => $name]);
        $this->cleanup[] = $uuid;

        $options = new ListOptions(filters: [
            new FilterEntry('name', FilterOperator::Eq, [$name]),
        ]);
        $list = self::$client->objects->list($options);

        $found = false;
        foreach ($list as $item) {
            if ($item['uuid'] === $uuid) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Created object should be found with filter');
    }

    public function testObjectsCount(): void
    {
        $count = self::$client->objects->count();
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testObjectFiles(): void
    {
        $name = 'PHP SDK Files ' . $this->uniqueSuffix();
        $uuid = self::$client->objects->create(['name' => $name]);
        $this->cleanup[] = $uuid;

        $fileUuid = self::$client->files->upload('test.txt', 'Hello, World!');

        $attachment = new FileAttachment('file', $fileUuid);
        self::$client->objects->addFiles($uuid, [$attachment]);
        self::$client->objects->removeFiles($uuid, [$attachment]);
    }
}
