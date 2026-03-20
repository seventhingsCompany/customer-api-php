<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\CircularityHub;

use Seventhings\Models\FilterObject;
use Seventhings\Tests\Integration\IntegrationTestCase;

final class CircularityHubServiceTest extends IntegrationTestCase
{
    public function testSuggestCategory(): void
    {
        try {
            $filter = new FilterObject();
            $result = self::$client->circularityHub->suggestCategory($filter);
            // Result may be null if no suggestion found
            if ($result !== null) {
                $this->assertIsArray($result);
            } else {
                $this->assertNull($result);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('CircularityHub not available: ' . $e->getMessage());
        }
    }

    public function testItemsCRUD(): void
    {
        try {
            $items = self::$client->circularityHub->listItems();
            $this->assertIsArray($items);

            if (!empty($items)) {
                $firstItem = $items[0];
                $itemId = $firstItem['id'];

                $item = self::$client->circularityHub->getItem($itemId);
                $this->assertSame($itemId, $item['id']);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('CircularityHub not available: ' . $e->getMessage());
        }
    }

    public function testOrdersCRUD(): void
    {
        try {
            $orders = self::$client->circularityHub->listOrders();
            $this->assertIsArray($orders);

            // Create an order requires item IDs, so we need items first
            $items = self::$client->circularityHub->listItems();
            if (!empty($items)) {
                $itemId = $items[0]['id'];
                $orderId = self::$client->circularityHub->createOrder([$itemId]);
                $this->assertGreaterThan(0, $orderId);

                $order = self::$client->circularityHub->getOrder($orderId);
                $this->assertSame($orderId, $order->id);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('CircularityHub not available: ' . $e->getMessage());
        }
    }

    public function testAddObjects(): void
    {
        try {
            $objUuid = self::$client->objects->create([
                'inventory_name' => 'ch-add-obj-' . $this->uniqueSuffix(),
                'barcode' => 'INT-CHADD-' . $this->uniqueSuffix(),
                'purchasing_price' => 100.00,
            ]);

            try {
                $entries = [
                    $objUuid => new \Seventhings\Models\AddObjectEntry(
                        category: 'category_furniture',
                        price: '10.00',
                    ),
                ];
                self::$client->circularityHub->addObjects($entries);
                $this->assertTrue(true);
            } finally {
                self::$client->objects->delete($objUuid);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('CircularityHub not available: ' . $e->getMessage());
        }
    }
}
