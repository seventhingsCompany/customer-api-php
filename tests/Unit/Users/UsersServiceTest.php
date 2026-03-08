<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Users;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Models\Enums\UserSortBy;
use Seventhings\Models\Enums\UserSortOrder;
use Seventhings\Models\UserListOptions;
use Seventhings\Models\UserListResponse;
use Seventhings\Models\UserResponse;
use Seventhings\Users\UsersService;

final class UsersServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): UsersService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new UsersService($httpClient);
    }

    #[Test]
    public function listReturnsUserListResponse(): void
    {
        $data = [
            'items' => [
                ['uuid' => 'u1', 'id' => 1, 'email' => 'a@b.com', 'firstname' => 'A', 'lastname' => 'B', 'display_name' => 'A B'],
                ['uuid' => 'u2', 'id' => 2, 'email' => 'c@d.com'],
            ],
            'page' => 1,
            'per_page' => 25,
            'sort_by' => 'id',
            'order' => 'asc',
            'total' => 2,
        ];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->list();

        $this->assertInstanceOf(UserListResponse::class, $result);
        $this->assertCount(2, $result->items);
        $this->assertSame(1, $result->page);
        $this->assertSame(25, $result->perPage);
        $this->assertSame('id', $result->sortBy);
        $this->assertSame('asc', $result->order);
        $this->assertSame(2, $result->total);

        $this->assertInstanceOf(UserResponse::class, $result->items[0]);
        $this->assertSame('u1', $result->items[0]->uuid);
        $this->assertSame('A', $result->items[0]->firstname);
        $this->assertSame('A B', $result->items[0]->displayName);

        $this->assertNull($result->items[1]->firstname);
        $this->assertNull($result->items[1]->displayName);

        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringEndsWith('/users', (string) $request->getUri());
    }

    #[Test]
    public function listWithOptionsAppendsQueryString(): void
    {
        $data = ['items' => [], 'page' => 1, 'per_page' => 10, 'sort_by' => 'email', 'order' => 'desc', 'total' => 0];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $options = new UserListOptions(
            page: 2,
            perPage: 10,
            sortBy: UserSortBy::Email,
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
    public function getReturnsSingleUser(): void
    {
        $data = ['uuid' => 'u1', 'id' => 42, 'email' => 'test@example.com', 'firstname' => 'Test', 'lastname' => 'User', 'display_name' => 'Test User'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->get('u1');

        $this->assertInstanceOf(UserResponse::class, $result);
        $this->assertSame('u1', $result->uuid);
        $this->assertSame(42, $result->id);
        $this->assertSame('test@example.com', $result->email);
        $this->assertStringEndsWith('/user/u1', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function getByIdUsesCorrectPath(): void
    {
        $data = ['uuid' => 'u1', 'id' => 42, 'email' => 'test@example.com'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->getById(42);

        $this->assertInstanceOf(UserResponse::class, $result);
        $this->assertSame(42, $result->id);
        $this->assertStringEndsWith('/user/by-id/42', (string) $this->history[0]['request']->getUri());
    }
}
