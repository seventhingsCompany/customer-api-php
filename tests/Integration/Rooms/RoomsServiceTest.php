<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Rooms;

use Seventhings\Tests\Integration\IntegrationTestCase;

final class RoomsServiceTest extends IntegrationTestCase
{
    public function testRoomsList(): void
    {
        $list = self::$client->rooms->list();
        $this->assertIsArray($list);
    }

    public function testRoomsCount(): void
    {
        $count = self::$client->rooms->count();
        $this->assertGreaterThanOrEqual(0, $count);
    }
}
