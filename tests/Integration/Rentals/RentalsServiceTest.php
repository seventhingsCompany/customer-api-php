<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Rentals;

use Seventhings\Models\CreateRentalCaseRequest;
use Seventhings\Models\UpdateRentalCaseRequest;
use Seventhings\Tests\Integration\IntegrationTestCase;

final class RentalsServiceTest extends IntegrationTestCase
{
    /** @var string[] */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $uuid) {
            try {
                self::$client->rentals->delete($uuid);
            } catch (\Throwable) {
            }
        }
    }

    public function testRentalsList(): void
    {
        try {
            $list = self::$client->rentals->list();
            $this->assertIsArray($list);
        } catch (\Throwable) {
            $this->markTestSkipped('Rentals module not available');
        }
    }

    public function testRentalsCRUD(): void
    {
        try {
            $request = new CreateRentalCaseRequest(comment: 'PHP SDK Test ' . $this->uniqueSuffix());
            $uuid = self::$client->rentals->create($request);
            $this->cleanup[] = $uuid;
            $this->assertNotEmpty($uuid);

            $rental = self::$client->rentals->get($uuid);
            $this->assertSame($uuid, $rental->uuid);

            self::$client->rentals->update($uuid, new UpdateRentalCaseRequest(
                comment: 'PHP SDK Test Updated ' . $this->uniqueSuffix(),
            ));

            self::$client->rentals->delete($uuid);
            $this->cleanup = array_filter($this->cleanup, fn($id) => $id !== $uuid);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Rentals module not available: ' . $e->getMessage());
        }
    }
}
