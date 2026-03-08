<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Locations;

use Seventhings\Tests\Integration\IntegrationTestCase;

final class LocationsServiceTest extends IntegrationTestCase
{
    /** @var string[] */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $uuid) {
            try {
                self::$client->locations->delete($uuid);
            } catch (\Throwable) {
            }
        }
    }

    public function testLocationsCRUD(): void
    {
        $name = 'PHP SDK Location ' . $this->uniqueSuffix();
        $uuid = self::$client->locations->create(['name' => $name]);
        $this->cleanup[] = $uuid;
        $this->assertNotEmpty($uuid);

        $location = self::$client->locations->get($uuid);
        $this->assertSame($uuid, $location['location_uuid']);

        self::$client->locations->patch($uuid, ['name' => $name . ' Updated']);

        $list = self::$client->locations->list();
        $this->assertNotEmpty($list);

        $count = self::$client->locations->count();
        $this->assertGreaterThan(0, $count);

        self::$client->locations->delete($uuid);
        $this->cleanup = array_filter($this->cleanup, fn($id) => $id !== $uuid);
    }

    public function testLocationsCount(): void
    {
        $count = self::$client->locations->count();
        $this->assertGreaterThanOrEqual(0, $count);
    }
}
