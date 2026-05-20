<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Persons;

use Seventhings\Models\Enums\UserSortOrder;
use Seventhings\Models\PersonListOptions;
use Seventhings\Tests\Integration\IntegrationTestCase;

final class PersonsServiceTest extends IntegrationTestCase
{
    public function testPersonsList(): void
    {
        $result = self::$client->persons->list();
        $this->assertGreaterThanOrEqual(0, $result->total);
    }

    public function testPersonsListWithOptions(): void
    {
        $result = self::$client->persons->list(new PersonListOptions(
            page: 1,
            perPage: 5,
            sortBy: 'id',
            order: UserSortOrder::Asc,
        ));
        $this->assertSame(1, $result->page);
        $this->assertSame(5, $result->perPage);
    }

    public function testPersonsGetAndGetById(): void
    {
        $result = self::$client->persons->list();
        if (empty($result->items)) {
            $this->markTestSkipped('no persons available to test get/getById');
        }

        $first = $result->items[0];

        $byUuid = self::$client->persons->get($first->uuid);
        $this->assertSame($first->uuid, $byUuid->uuid);
        $this->assertSame($first->id, $byUuid->id);

        $byId = self::$client->persons->getById($first->id);
        $this->assertSame($first->uuid, $byId->uuid);
        $this->assertSame($first->id, $byId->id);
    }
}
