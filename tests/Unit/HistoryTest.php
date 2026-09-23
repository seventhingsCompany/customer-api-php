<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Locations\LocationsService;
use Seventhings\Models\ApiException;
use Seventhings\Models\HistoryListOptions;
use Seventhings\Models\LocationHistoryEntry;
use Seventhings\Models\PersonHistoryEntry;
use Seventhings\Models\RentalCaseHistoryEntry;
use Seventhings\Models\RoomHistoryEntry;
use Seventhings\Models\TaskHistoryEntry;
use Seventhings\Objects\ObjectsService;
use Seventhings\Persons\PersonsService;
use Seventhings\Rentals\RentalsService;
use Seventhings\Rooms\RoomsService;
use Seventhings\Tasks\TasksService;

final class HistoryTest extends TestCase
{
    private array $requests = [];

    private function createService(string $serviceClass, GuzzleResponse ...$responses): object
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requests));
        $http = new HttpClient('https://example.com', new GuzzleClient(['handler' => $stack]));
        $http->setToken('history-token');

        return new $serviceClass($http);
    }

    public static function resources(): iterable
    {
        yield 'objects' => [ObjectsService::class, 'object', null, null, null];
        yield 'rooms' => [RoomsService::class, 'room', RoomHistoryEntry::class, 'room_uuid', 'roomUuid'];
        yield 'locations' => [LocationsService::class, 'location', LocationHistoryEntry::class, 'location_uuid', 'locationUuid'];
        yield 'persons' => [PersonsService::class, 'person', PersonHistoryEntry::class, 'person_uuid', 'personUuid'];
        yield 'tasks' => [TasksService::class, 'task-management/task', TaskHistoryEntry::class, 'task_uuid', 'taskUuid'];
        yield 'rentals' => [RentalsService::class, 'rental-management/rental-case', RentalCaseHistoryEntry::class, 'rental_case_uuid', 'rentalCaseUuid'];
    }

    #[Test]
    #[DataProvider('resources')]
    public function historyDecodesPageAndSendsAuthenticatedRequest(
        string $serviceClass,
        string $path,
        ?string $entryClass,
        ?string $uuidKey,
        ?string $uuidProperty,
    ): void {
        $entries = $entryClass === null ? [
            ['type' => 'asset', 'date' => '2026-09-15T12:00:00+00:00', 'properties' => ['name' => 'Desk']],
            ['type' => 'task', 'properties' => ['title' => 'Inspect']],
            ['type' => 'rental_case', 'properties' => ['comment' => 'Team event']],
            ['type' => 'object_merge', 'user_id' => 42, 'absorbedObjectData' => ['custom' => ['value']]],
        ] : [[
            $uuidKey => 'entity-uuid',
            'user_uuid' => 'user-uuid',
            'occurred_at' => '2026-09-15T12:00:00+00:00',
            'event_name' => 'Modified',
            'description' => 'Changed name',
            'details' => '{"name":"Desk"}',
        ], [$uuidKey => 'entity-uuid', 'details' => '']];

        $service = $this->createService($serviceClass, new GuzzleResponse(200, [], json_encode([
            'items' => $entries, 'page' => 2, 'per_page' => 10, 'total' => 14,
        ])));
        $page = $service->history('entity/ ?#%', new HistoryListOptions(page: 2, perPage: 10));

        $this->assertSame(2, $page->page);
        $this->assertSame(10, $page->perPage);
        $this->assertSame(14, $page->total);
        $this->assertCount(count($entries), $page->items);
        if ($entryClass === null) {
            $this->assertSame($entries, $page->items);
        } else {
            $entry = $page->items[0];
            $this->assertInstanceOf($entryClass, $entry);
            $this->assertSame('entity-uuid', $entry->$uuidProperty);
            $this->assertSame('user-uuid', $entry->userUuid);
            $this->assertSame('2026-09-15T12:00:00+00:00', $entry->occurredAt);
            $this->assertSame('Modified', $entry->eventName);
            $this->assertSame('Changed name', $entry->description);
            $this->assertSame('{"name":"Desk"}', $entry->details);
            $this->assertSame('', $page->items[1]->details);
        }

        $request = $this->requests[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/customer-api/v1/' . $path . '/entity%2F%20%3F%23%25/history', $request->getUri()->getPath());
        $this->assertSame('page=2&per_page=10', $request->getUri()->getQuery());
        $this->assertSame('Bearer history-token', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    #[Test]
    #[DataProvider('resources')]
    public function emptyHistoryUsesApiDefaults(string $serviceClass): void
    {
        $service = $this->createService($serviceClass, new GuzzleResponse(200, [], '{"items":[],"page":1,"per_page":50,"total":0}'));
        $page = $service->history('entity-uuid');

        $this->assertSame([], $page->items);
        $this->assertSame(1, $page->page);
        $this->assertSame(50, $page->perPage);
        $this->assertSame(0, $page->total);
        $this->assertSame('', $this->requests[0]['request']->getUri()->getQuery());
    }

    #[Test]
    #[DataProvider('resources')]
    public function malformedHistoryThrows(string $serviceClass): void
    {
        $service = $this->createService($serviceClass, new GuzzleResponse(200, [], '{'));
        $this->expectException(\JsonException::class);
        $service->history('entity-uuid');
    }

    #[Test]
    #[DataProvider('resources')]
    public function historyPreservesApiErrors(string $serviceClass): void
    {
        $service = $this->createService($serviceClass, new GuzzleResponse(403, [], '{"message":"forbidden"}'));
        try {
            $service->history('entity-uuid');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(403, $e->statusCode);
            $this->assertSame('{"message":"forbidden"}', $e->body);
        }
    }

    public static function paginationOptions(): iterable
    {
        yield 'unset' => [new HistoryListOptions(), ''];
        yield 'page only' => [new HistoryListOptions(page: 2), 'page=2'];
        yield 'per page only' => [new HistoryListOptions(perPage: 200), 'per_page=200'];
        yield 'both' => [new HistoryListOptions(page: 2, perPage: 10), 'page=2&per_page=10'];
    }

    #[Test]
    #[DataProvider('paginationOptions')]
    public function historyOptionsEncodeOnlySuppliedValues(HistoryListOptions $options, string $query): void
    {
        $service = $this->createService(ObjectsService::class, new GuzzleResponse(200, [], '{"items":[]}'));
        $service->history('uuid', $options);
        $this->assertSame($query, $this->requests[0]['request']->getUri()->getQuery());
    }
}
