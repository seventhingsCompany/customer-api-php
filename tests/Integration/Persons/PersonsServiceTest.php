<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Persons;

use Seventhings\Models\ApiException;
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

    public function testPersonsCount(): void
    {
        $count = self::$client->persons->count();
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testPersonCreatePatchDeleteLifecycle(): void
    {
        $email = 'sdk-int-' . uniqid() . '@example.com';

        // Field keys are template-defined; the default person template uses
        // first_name / last_name (not firstname / lastname).
        try {
            $uuid = self::$client->persons->create([
                'email' => $email,
                'first_name' => 'Integration',
                'last_name' => 'Test',
            ]);
        } catch (ApiException $e) {
            $this->markTestSkipped('person create not permitted on this instance: ' . $e->getMessage());
        }

        $this->assertNotEmpty($uuid);

        try {
            // PATCH returns an empty body, so re-fetch to verify the update.
            self::$client->persons->patch($uuid, ['last_name' => 'Patched']);
            $updated = self::$client->persons->get($uuid);
            $this->assertSame('Patched', $updated->lastname);
        } finally {
            self::$client->persons->delete($uuid);
        }

        $this->expectException(ApiException::class);
        self::$client->persons->get($uuid);
    }
}
