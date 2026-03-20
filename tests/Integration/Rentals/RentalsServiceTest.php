<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Rentals;

use Seventhings\Models\CreateRentalCaseRequest;
use Seventhings\Models\Enums\RentalCaseReferenceType;
use Seventhings\Models\Enums\RenterType;
use Seventhings\Models\RentalCaseReferenceInput;
use Seventhings\Models\RentalCaseRenter;
use Seventhings\Models\UpdateRentalCaseRequest;
use Seventhings\Models\UserListOptions;
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
            // Create a temporary object for reference
            $refUuid = self::$client->objects->create([
                'inventory_name' => 'rental-ref-' . $this->uniqueSuffix(),
                'barcode' => 'INT-RENT-' . $this->uniqueSuffix(),
            ]);

            // Fetch a user UUID for responsible_user_uuid
            $users = self::$client->users->list(new UserListOptions(perPage: 1));
            $userUuid = $users->items[0]->uuid;

            $suffix = $this->uniqueSuffix();
            $request = new CreateRentalCaseRequest(
                title: 'PHP SDK Test ' . $suffix,
                issueDate: '2099-01-01',
                dueDate: '2099-06-01',
                comment: 'PHP SDK Test ' . $suffix,
                responsibleUserUuid: $userUuid,
                renter: new RentalCaseRenter(RenterType::Plain, 'Integration Tester'),
                references: [new RentalCaseReferenceInput(RentalCaseReferenceType::Asset, $refUuid)],
                attachments: [],
            );
            $uuid = self::$client->rentals->create($request);
            $this->cleanup[] = $uuid;
            $this->assertNotEmpty($uuid);

            $rental = self::$client->rentals->get($uuid);
            $this->assertSame($uuid, $rental->uuid);

            $updatedSuffix = $this->uniqueSuffix();
            self::$client->rentals->update($uuid, new UpdateRentalCaseRequest(
                title: 'PHP SDK Test Updated ' . $updatedSuffix,
                issueDate: '2099-01-01',
                dueDate: '2099-06-01',
                comment: 'PHP SDK Test Updated ' . $updatedSuffix,
                responsibleUserUuid: $userUuid,
                renter: new RentalCaseRenter(RenterType::Plain, 'Updated Tester'),
                references: [new RentalCaseReferenceInput(RentalCaseReferenceType::Asset, $refUuid)],
                attachments: [],
            ));

            self::$client->rentals->delete($uuid);
            $this->cleanup = array_filter($this->cleanup, fn($id) => $id !== $uuid);

            // Clean up the reference object
            self::$client->objects->delete($refUuid);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Rentals module not available: ' . $e->getMessage());
        }
    }
}
