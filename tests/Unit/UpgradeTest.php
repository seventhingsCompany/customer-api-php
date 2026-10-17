<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\CatalogItems\CatalogItemsService;
use Seventhings\Files\FilesService;
use Seventhings\HttpClient;
use Seventhings\Models\CatalogItemListResponse;
use Seventhings\Models\CreateRentalCaseRequest;
use Seventhings\Models\CreateTaskRequest;
use Seventhings\Models\Enums\FilterOperator;
use Seventhings\Models\Enums\RenterType;
use Seventhings\Models\Enums\SortDirection;
use Seventhings\Models\Enums\TimeIntervalUnit;
use Seventhings\Models\FilterEntry;
use Seventhings\Models\ListOptions;
use Seventhings\Models\RentalCaseRenter;
use Seventhings\Models\RentalCaseResponse;
use Seventhings\Models\TimeInterval;
use Seventhings\Models\UpdateRentalCaseRequest;
use Seventhings\Models\UpdateTaskRequest;

final class UpgradeTest extends TestCase
{
    private array $history = [];

    private function http(array $responses): HttpClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $http = new HttpClient('https://example.com', new GuzzleClient(['handler' => $stack]));
        $http->setToken('tok');
        return $http;
    }

    private function request(int $i): \Psr\Http\Message\RequestInterface
    {
        return $this->history[$i]['request'];
    }

    #[Test]
    public function catalogItemsCrud(): void
    {
        $item = ['uuid' => 'c-1', 'fields' => ['id' => 7, 'catalog_object_name' => 'Chair']];
        $service = new CatalogItemsService($this->http([
            new GuzzleResponse(200, [], json_encode(['items' => [$item], 'page' => 2, 'per_page' => 10, 'total' => 11, 'sort' => ['id' => 'DESC']])),
            new GuzzleResponse(200, [], json_encode($item)),
            new GuzzleResponse(201, ['Location-UUID' => 'c-1'], ''),
            new GuzzleResponse(201, ['Location' => '/customer-api/v1/catalog-item/c-2'], ''),
            new GuzzleResponse(204),
            new GuzzleResponse(204),
        ]));

        $page = $service->list(new ListOptions(page: 2, perPage: 10, sort: ['id' => SortDirection::Desc], filters: [new FilterEntry('catalog_object_name', FilterOperator::Like, ['Ch'])]));
        $this->assertSame(11, $page->total);
        $this->assertSame(['id' => 'DESC'], $page->sort);
        $this->assertSame('Chair', $page->items[0]->fields->string('catalog_object_name'));
        $query = urldecode($this->request(0)->getUri()->getQuery());
        $this->assertSame('/customer-api/v1/catalog-items', $this->request(0)->getUri()->getPath());
        $this->assertStringContainsString('page=2', $query);
        $this->assertStringContainsString('sort[id]=DESC', $query);

        $got = $service->get('c-1');
        $this->assertSame('c-1', $got->uuid);
        $this->assertSame(7, $got->fields->int('id'));

        $this->assertSame('c-1', $service->create(['catalog_object_name' => 'Chair']));
        $this->assertSame('{"fields":{"catalog_object_name":"Chair"}}', (string) $this->request(2)->getBody());
        $this->assertSame('c-2', $service->create([]));
        $this->assertSame('{"fields":{}}', (string) $this->request(3)->getBody());

        $service->update('c-1', ['catalog_object_name' => 'Desk']);
        $this->assertSame('PUT', $this->request(4)->getMethod());
        $this->assertSame('/customer-api/v1/catalog-item/c-1', $this->request(4)->getUri()->getPath());
        $this->assertSame('{"fields":{"catalog_object_name":"Desk"}}', (string) $this->request(4)->getBody());

        $service->delete('c-1');
        $this->assertSame('DELETE', $this->request(5)->getMethod());
    }

    #[Test]
    public function catalogEmptySorting(): void
    {
        foreach (['{"items":[],"sort":[]}', '{"items":[],"sort":{}}', '{"items":[],"sort":null}', '{"items":[]}'] as $raw) {
            $this->assertSame([], CatalogItemListResponse::fromArray(json_decode($raw, true))->sort);
        }
        foreach (['{"sort":["id"]}', '{"sort":42}'] as $raw) {
            try {
                CatalogItemListResponse::fromArray(json_decode($raw, true));
                $this->fail('expected error for ' . $raw);
            } catch (\UnexpectedValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function catalogAllPaginates(): void
    {
        $page = fn(array $uuids) => new GuzzleResponse(200, [], json_encode(['items' => array_map(fn($u) => ['uuid' => $u, 'fields' => []], $uuids)]));
        $service = new CatalogItemsService($this->http([$page(['a', 'b']), $page(['c'])]));
        $uuids = array_map(fn($i) => $i->uuid, iterator_to_array($service->all(new ListOptions(perPage: 2)), false));
        $this->assertSame(['a', 'b', 'c'], $uuids);
    }

    #[Test]
    public function thumbnailWithSize(): void
    {
        $service = new FilesService($this->http([new GuzzleResponse(200, [], 'img'), new GuzzleResponse(200, [], 'default')]));
        $this->assertSame('img', $service->getThumbnail('f-1', 320, 240));
        $this->assertSame('/customer-api/v1/file/f-1/thumbnail', $this->request(0)->getUri()->getPath());
        $this->assertSame('width=320&height=240', $this->request(0)->getUri()->getQuery());
        $this->assertSame('default', $service->getThumbnail('f-1'));
        $this->assertSame('', $this->request(1)->getUri()->getQuery());

        foreach ([[0, 10], [10, 2001], [10, null], [null, 10]] as [$w, $h]) {
            try {
                $service->getThumbnail('f-1', $w, $h);
                $this->fail('expected InvalidArgumentException');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertCount(2, $this->history);
    }

    #[Test]
    public function timedRentalCaseFields(): void
    {
        $renter = new RentalCaseRenter(RenterType::Plain, 'SDK test');
        $args = ['title' => 't', 'issueDate' => '2026-10-16', 'dueDate' => '2026-10-16', 'comment' => '', 'responsibleUserUuid' => 'u', 'renter' => $renter, 'references' => [], 'attachments' => []];
        foreach ([CreateRentalCaseRequest::class, UpdateRentalCaseRequest::class] as $class) {
            $timed = (new $class(...$args, isFullDay: false, issueAt: '2026-10-16T08:00:00+00:00', dueAt: '2026-10-16T10:00:00+00:00'))->toArray();
            $this->assertFalse($timed['is_full_day']);
            $this->assertSame('2026-10-16T08:00:00+00:00', $timed['issue_at']);
            $this->assertSame('2026-10-16T10:00:00+00:00', $timed['due_at']);

            $plain = (new $class(...$args))->toArray();
            foreach (['is_full_day', 'issue_at', 'due_at'] as $key) {
                $this->assertArrayNotHasKey($key, $plain);
            }
            $this->assertSame(['type' => 'plain', 'value' => 'SDK test'], $plain['renter']);
        }

        $base = ['uuid' => 'r', 'status' => 'borrowed', 'title' => 't'];
        $timed = RentalCaseResponse::fromArray($base + ['is_full_day' => false, 'issue_at' => '2026-10-16T08:00:00+00:00', 'due_at' => '2026-10-16T10:00:00+00:00']);
        $this->assertFalse($timed->isFullDay);
        $this->assertSame('2026-10-16T08:00:00+00:00', $timed->issueAt);
        $this->assertSame('2026-10-16T10:00:00+00:00', $timed->dueAt);
        $legacy = RentalCaseResponse::fromArray($base);
        $this->assertTrue($legacy->isFullDay);
        $this->assertNull($legacy->issueAt);
    }

    #[Test]
    public function taskOptionalReminders(): void
    {
        foreach ([null, [], [new TimeInterval(TimeIntervalUnit::Days, 0)]] as $reminders) {
            foreach ([CreateTaskRequest::class, UpdateTaskRequest::class] as $class) {
                $data = (new $class('t', null, [], [], $reminders, null))->toArray();
                if ($reminders === null) {
                    $this->assertArrayNotHasKey('reminders', $data);
                } else {
                    $this->assertSame(array_map(fn($r) => $r->toArray(), $reminders), $data['reminders']);
                }
            }
        }
        $this->assertSame('{"reminders":[]}', json_encode(['reminders' => (new CreateTaskRequest('t', null, [], [], [], null))->toArray()['reminders']]));
    }
}
