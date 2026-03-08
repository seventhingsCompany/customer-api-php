<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Users;

use Seventhings\Tests\Integration\IntegrationTestCase;

final class UsersServiceTest extends IntegrationTestCase
{
    public function testUsersList(): void
    {
        $result = self::$client->users->list();
        $this->assertNotEmpty($result->items);
        $this->assertGreaterThan(0, $result->total);
    }

    public function testUsersGetAndGetById(): void
    {
        $result = self::$client->users->list();
        $this->assertNotEmpty($result->items);

        $first = $result->items[0];

        $byUuid = self::$client->users->get($first->uuid);
        $this->assertSame($first->uuid, $byUuid->uuid);
        $this->assertSame($first->id, $byUuid->id);

        $byId = self::$client->users->getById($first->id);
        $this->assertSame($first->uuid, $byId->uuid);
        $this->assertSame($first->id, $byId->id);
    }
}
