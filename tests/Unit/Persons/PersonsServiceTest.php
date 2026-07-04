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
use Seventhings\Models\Enums\SortDirection;
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
            'sort' => ['id' => 'ASC'],
            'total' => 2,
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->list();

        $this->assertInstanceOf(PersonListResponse::class, $result);
        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->total);
        // The persons endpoint echoes the applied sort as a field => direction map.
        $this->assertSame(['id' => 'ASC'], $result->sort);
        $this->assertInstanceOf(PersonResponse::class, $result->items[0]);
        $this->assertSame('p1', $result->items[0]->uuid);
        $this->assertSame('Alice', $result->items[0]->firstname);
        $this->assertNull($result->items[1]->firstname);

        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringEndsWith('/persons', (string) $request->getUri());
    }

    #[Test]
    public function listPreservesTemplateDefinedCustomFields(): void
    {
        // Person fields are template-defined and arrive as flat top-level keys.
        // A custom key not surfaced as a typed prop must survive via ->fields.
        $data = [
            'items' => [
                [
                    'person_uuid' => 'p1',
                    'id' => 1,
                    'email' => 'a@b.com',
                    'first_name' => 'Alice',
                    'last_name' => 'Smith',
                    'cost_center' => 'CC-100',
                ],
            ],
            'page' => 1,
            'per_page' => 25,
            'sort' => [],
            'total' => 1,
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $person = $service->list()->items[0];

        // Typed convenience props still resolve (backward compatible).
        $this->assertSame('Alice', $person->firstname);
        // Custom field preserved via both the bag and the accessor.
        $this->assertSame('CC-100', $person->fields['cost_center']);
        $this->assertSame('CC-100', $person->field('cost_center'));
    }

    #[Test]
    public function getPreservesFullRawFieldMap(): void
    {
        $data = [
            'person_uuid' => 'p1',
            'id' => 42,
            'email' => 'a@b.com',
            'first_name' => 'Alice',
            'cost_center' => 'CC-100',
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $person = $service->get('p1');

        $this->assertSame('CC-100', $person->field('cost_center'));
        // The full untouched wire map is retained verbatim.
        $this->assertSame($data, $person->fields);
        // Missing keys read back as null, not an undefined-index error.
        $this->assertNull($person->field('does_not_exist'));
    }

    #[Test]
    public function listWithOptionsAppendsQueryString(): void
    {
        $data = ['items' => [], 'page' => 2, 'per_page' => 10, 'sort' => ['email' => 'DESC'], 'total' => 0];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $options = new PersonListOptions(
            page: 2,
            perPage: 10,
            sort: ['email' => SortDirection::Desc],
        );
        $service->list($options);

        $uri = (string) $this->history[0]['request']->getUri();
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('per_page=10', $uri);
        // Persons use the deep-object sort[field]=DIR format, not sort_by/order.
        // Guzzle percent-encodes the brackets on the wire (the API accepts
        // both forms and echoes the sort back either way).
        $this->assertStringContainsString('sort%5Bemail%5D=DESC', $uri);
        $this->assertStringNotContainsString('sort_by=', $uri);
    }

    #[Test]
    public function allWalksPagesYieldingPersonResponses(): void
    {
        $page = fn(array $uuids) => json_encode([
            'items' => array_map(fn($u) => ['person_uuid' => $u, 'id' => 1, 'email' => 'x@y.com'], $uuids),
            'total' => 3,
        ]);
        // perPage=2: full page then short page ends iteration.
        $service = $this->createService([
            new GuzzleResponse(200, [], $page(['p1', 'p2'])),
            new GuzzleResponse(200, [], $page(['p3'])),
        ]);

        $persons = iterator_to_array($service->all(new PersonListOptions(perPage: 2)), false);

        $this->assertCount(3, $persons);
        $this->assertInstanceOf(PersonResponse::class, $persons[0]);
        $this->assertSame(['p1', 'p2', 'p3'], array_map(fn($p) => $p->uuid, $persons));

        $this->assertCount(2, $this->history);
        $this->assertStringContainsString('page=1', (string) $this->history[0]['request']->getUri());
        $this->assertStringContainsString('page=2', (string) $this->history[1]['request']->getUri());
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
        $this->assertStringEndsWith('/person', (string) $request->getUri());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('fields', $body);
        $this->assertSame('max@example.com', $body['fields']['email']);
        $this->assertSame('Max', $body['fields']['firstname']);
    }

    #[Test]
    public function countReturnsCount(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], json_encode(['count' => 7]))]);

        $count = $service->count();

        $this->assertSame(7, $count);

        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringEndsWith('/persons/count', (string) $request->getUri());
    }

    #[Test]
    public function countAppendsQueryString(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], json_encode(['count' => 0]))]);

        $service->count(new PersonListOptions(page: 3, perPage: 50));

        $uri = (string) $this->history[0]['request']->getUri();
        $this->assertStringContainsString('/persons/count?', $uri);
        $this->assertStringContainsString('page=3', $uri);
        $this->assertStringContainsString('per_page=50', $uri);
    }

    #[Test]
    public function patchSendsBareFields(): void
    {
        // The API responds 200 with an empty body, so patch returns void.
        $service = $this->createService([new GuzzleResponse(200, [], '{}')]);

        $service->patch('p1', ['last_name' => 'Jones']);

        $request = $this->history[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringEndsWith('/person/p1', (string) $request->getUri());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertArrayNotHasKey('fields', $body);
        $this->assertSame('Jones', $body['last_name']);
    }

    #[Test]
    public function deleteUsesCorrectPath(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->delete('p1');

        $request = $this->history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringEndsWith('/person/p1', (string) $request->getUri());
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
