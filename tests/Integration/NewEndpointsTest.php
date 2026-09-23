<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Seventhings\Models\CreateReportRequest;
use Seventhings\Models\HistoryListOptions;
use Seventhings\Models\ListOptions;
use Seventhings\Models\PersonListOptions;
use Seventhings\Models\ReportTemplate;

/** Exercises the new endpoints using existing instance data. */
final class NewEndpointsTest extends IntegrationTestCase
{
    private function entityUuid(array $fields, string $key): string
    {
        $uuid = $fields[$key] ?? $fields['uuid'] ?? '';
        $this->assertIsString($uuid);
        $this->assertNotSame('', $uuid, "Response has no $key or uuid");
        return $uuid;
    }

    public static function historyResources(): iterable
    {
        yield 'objects' => ['objects', 'asset_uuid'];
        yield 'rooms' => ['rooms', 'room_uuid'];
        yield 'locations' => ['locations', 'location_uuid'];
        yield 'persons' => ['persons', null];
        yield 'tasks' => ['tasks', null];
        yield 'rentals' => ['rentals', null];
    }

    #[Test]
    #[DataProvider('historyResources')]
    public function historyPaginationMatchesCombinedPage(string $module, ?string $uuidKey): void
    {
        $service = self::$client->$module;
        $items = match ($module) {
            'persons' => $service->list(new PersonListOptions(perPage: 10))->items,
            'tasks' => array_slice($service->list(), 0, 10),
            default => $service->list(new ListOptions(page: 1, perPage: 10)),
        };
        if ($items === []) {
            $this->markTestSkipped("Instance has no $module");
        }

        foreach ($items as $item) {
            $uuid = $uuidKey === null ? $item->uuid : $this->entityUuid($item, $uuidKey);
            $this->assertNotSame('', $uuid);
            $first = $service->history($uuid, new HistoryListOptions(page: 1, perPage: 1));
            $this->assertSame(1, $first->page);
            $this->assertSame(1, $first->perPage);
            $this->assertGreaterThanOrEqual(0, $first->total);
            $this->assertCount(min($first->total, 1), $first->items);
            if ($first->total < 2) {
                // Out-of-range pages may be clamped by the API. Only test
                // page 2 when the resource actually has multiple changes.
                continue;
            }

            $second = $service->history($uuid, new HistoryListOptions(page: 2, perPage: 1));
            $this->assertSame(2, $second->page);
            $this->assertSame(1, $second->perPage);
            $this->assertGreaterThanOrEqual(2, $second->total);
            $this->assertCount(1, $second->items);
            $combined = $service->history($uuid, new HistoryListOptions(page: 1, perPage: 2));
            $this->assertSame(1, $combined->page);
            $this->assertSame(2, $combined->perPage);
            $this->assertEquals([...$first->items, ...$second->items], $combined->items);
            return;
        }
    }

    #[Test]
    public function barcodeLookupReturnsMatchingObject(): void
    {
        $objects = self::$client->objects->list(new ListOptions(perPage: 10));
        foreach ($objects as $object) {
            $barcode = $object['barcode'] ?? null;
            if (!is_string($barcode) || $barcode === '') {
                continue;
            }
            $found = self::$client->objects->getByBarcode($barcode);
            $this->assertSame($this->entityUuid($object, 'asset_uuid'), $this->entityUuid($found, 'asset_uuid'));
            return;
        }
        $this->markTestSkipped('Sample contains no objects with a barcode');
    }

    #[Test]
    public function listsReportTemplates(): void
    {
        $templates = self::$client->reports->listTemplates();
        $this->assertIsArray($templates);
        foreach ($templates as $template) {
            $this->assertInstanceOf(ReportTemplate::class, $template);
            $this->assertNotSame('', $template->uuid);
        }
    }

    #[Test]
    public function createsPdfFromExistingTemplateAndObject(): void
    {
        $templates = self::$client->reports->listTemplates();
        if ($templates === []) {
            $this->markTestSkipped('Instance has no report templates');
        }
        $objects = self::$client->objects->list(new ListOptions(perPage: 1));
        if ($objects === []) {
            $this->markTestSkipped('Instance has no objects');
        }
        $pdf = self::$client->reports->create(new CreateReportRequest(
            reportTemplateUuid: $templates[0]->uuid,
            objectUuids: [$this->entityUuid($objects[0], 'asset_uuid')],
        ));
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
