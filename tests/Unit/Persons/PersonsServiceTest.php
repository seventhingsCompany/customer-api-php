<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Persons;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Models\ApiException;
use Seventhings\Models\Enums\FilterOperator;
use Seventhings\Models\Enums\UserSortOrder;
use Seventhings\Models\FilterObject;
use Seventhings\Models\PersonListOptions;
use Seventhings\Models\PersonListResponse;
use Seventhings\Models\PersonResponse;
use Seventhings\Persons\PersonsService;

final class PersonsServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): PersonsService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new PersonsService($httpClient);
    }

    #[Test]
    public function listReturnsPersonListResponse(): void
    {
        $data = [
            'items' => [
                ['person_uuid' => 'p1', 'id' => 1, 'email' => 'a@b.com', 'first_name' => 'Alice', 'last_name' => 'Smith'],
                ['person_uuid' => 'p2', 'id' => 2, 'email' => 'c@d.com'],
            ],
            'page' => 1,
            'per_page' => 25,
            'sort_by' => 'id',
            'order' => 'asc',
            'total' => 2,
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->list();

        $this->assertInstanceOf(PersonListResponse::class, $result);
        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->total);
        $this->assertInstanceOf(PersonResponse::class, $result->items[0]);
        $this->assertSame('p1', $result->items[0]->uuid);
        $this->assertSame('Alice', $result->items[0]->firstname);
        $this->assertNull($result->items[1]->firstname);

        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringEndsWith('/persons', (string) $request->getUri());
    }

    #[Test]
    public function listWithOptionsAppendsQueryString(): void
    {
        $data = ['items' => [], 'page' => 2, 'per_page' => 10, 'sort_by' => 'email', 'order' => 'desc', 'total' => 0];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $options = new PersonListOptions(
            page: 2,
            perPage: 10,
            sortBy: 'email',
            order: UserSortOrder::Desc,
        );
        $service->list($options);

        $uri = (string) $this->history[0]['request']->getUri();
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('per_page=10', $uri);
        $this->assertStringContainsString('sort_by=email', $uri);
        $this->assertStringContainsString('order=desc', $uri);
    }

    #[Test]
    public function getReturnsSinglePerson(): void
    {
        $data = ['person_uuid' => 'p1', 'id' => 42, 'email' => 'a@b.com', 'first_name' => 'Alice', 'last_name' => 'Smith'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->get('p1');

        $this->assertInstanceOf(PersonResponse::class, $result);
        $this->assertSame('p1', $result->uuid);
        $this->assertSame(42, $result->id);
        $this->assertSame('Alice', $result->firstname);
        $this->assertStringEndsWith('/person/p1', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function getByIdUsesCorrectPath(): void
    {
        $data = ['person_uuid' => 'p42', 'id' => 42, 'email' => 'b@c.com'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->getById(42);

        $this->assertSame(42, $result->id);
        $this->assertStringEndsWith('/person/by-id/42', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function createPostsFieldsAndReturnsUuidFromLocationHeader(): void
    {
        $response = new GuzzleResponse(201, ['Location' => '/customer-api/v1/person/new-uuid-123'], '');
        $service = $this->createService([$response]);

        $uuid = $service->create([
            'email' => 'max@example.com',
            'firstname' => 'Max',
        ]);

        $this->assertSame('new-uuid-123', $uuid);

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/persons', (string) $request->getUri());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('fields', $body);
        $this->assertSame('max@example.com', $body['fields']['email']);
        $this->assertSame('Max', $body['fields']['firstname']);
    }

    #[Test]
    public function createUserSendsOnlyFilter(): void
    {
        $service = $this->createService([new GuzzleResponse(201, [], '')]);

        $filter = new FilterObject(
            filter: [
                'email' => [FilterOperator::Eq->value => 'tester@domain.de'],
            ],
            sort: ['name' => 'asc'],
        );

        $service->createUser($filter);

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/persons/create-user', (string) $request->getUri());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertArrayHasKey('filter', $body);
        $this->assertArrayNotHasKey('sort', $body);
        $this->assertSame('tester@domain.de', $body['filter']['email']['eq']);
    }

    #[Test]
    public function listThrowsApiExceptionOn500(): void
    {
        $service = $this->createService([new GuzzleResponse(500, [], 'internal error')]);

        $this->expectException(ApiException::class);
        $service->list();
    }

    #[Test]
    public function getThrowsApiExceptionOn404(): void
    {
        $service = $this->createService([new GuzzleResponse(404, [], 'not found')]);

        try {
            $service->get('nonexistent');
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(404, $e->statusCode);
        }
    }
}
