<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\CircularityHub;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\CircularityHub\CircularityHubService;
use Seventhings\HttpClient;
use Seventhings\Models\AddObjectEntry;
use Seventhings\Models\CircularityHubOrder;
use Seventhings\Models\FilterObject;
use Seventhings\Models\ListOptions;

final class CircularityHubServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): CircularityHubService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new CircularityHubService($httpClient);
    }

    private function sampleOrderData(): array
    {
        return [
            'id' => 1,
            'order_number' => 'ORD-001',
            'created_at' => '2024-03-01T00:00:00Z',
            'user_id' => 42,
            'total_price' => 99.99,
            'completed' => false,
            'cancelled' => false,
            'cancellation_reason' => null,
            'billing_data' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'street' => 'Main St',
                'house_number' => '42',
                'zip_code' => '12345',
                'city' => 'Berlin',
            ],
            'articles' => [
                ['id' => 1, 'name' => 'Chair'],
            ],
        ];
    }

    #[Test]
    public function suggestCategoryReturnsMap(): void
    {
        $responseData = ['category' => 'Electronics'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($responseData))]);

        $filter = new FilterObject(['key' => 'value'], ['name' => 'asc']);
        $result = $service->suggestCategory($filter);

        $this->assertSame(['category' => 'Electronics'], $result);

        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringEndsWith('/circularity-hub/suggest-category', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame(['key' => 'value'], $body['filter']);
        $this->assertSame(['name' => 'asc'], $body['sort']);
    }

    #[Test]
    public function suggestCategoryReturnsNullForEmptyArray(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '[]')]);

        $result = $service->suggestCategory(new FilterObject());

        $this->assertNull($result);
    }

    #[Test]
    public function suggestRestPriceReturnsMap(): void
    {
        $responseData = ['price' => '12.50'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($responseData))]);

        $result = $service->suggestRestPrice(['category' => 'Electronics']);

        $this->assertSame(['price' => '12.50'], $result);

        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringEndsWith('/circularity-hub/suggest-rest-price', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('Electronics', $body['category']);
    }

    #[Test]
    public function suggestRestPriceReturnsNullForEmptyArray(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '[]')]);

        $result = $service->suggestRestPrice(['category' => 'Unknown']);

        $this->assertNull($result);
    }

    #[Test]
    public function addObjectsSendsPost(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '{}')]);

        $entries = [
            'obj-1' => new AddObjectEntry('Chair', '50.00'),
            'obj-2' => new AddObjectEntry('Table', '120.00'),
        ];

        $service->addObjects($entries);

        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringEndsWith('/circularity-hub/add-objects-to-circularity-hub', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame(['category' => 'Chair', 'price' => '50.00'], $body['obj-1']);
        $this->assertSame(['category' => 'Table', 'price' => '120.00'], $body['obj-2']);
    }

    #[Test]
    public function listItemsReturnsUntypedArray(): void
    {
        $data = ['items' => [['id' => 1, 'name' => 'Item 1'], ['id' => 2, 'name' => 'Item 2']]];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->listItems();

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('Item 2', $result[1]['name']);

        $this->assertStringEndsWith('/circularity-hub/items', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function listItemsWithOptions(): void
    {
        $data = ['items' => []];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $service->listItems(new ListOptions(page: 2, perPage: 10));

        $uri = (string) $this->history[0]['request']->getUri();
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('per_page=10', $uri);
    }

    #[Test]
    public function getItemReturnsArray(): void
    {
        $itemData = ['id' => 5, 'name' => 'Widget', 'category' => 'Electronics'];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($itemData))]);

        $result = $service->getItem(5);

        $this->assertSame(5, $result['id']);
        $this->assertSame('Widget', $result['name']);
        $this->assertStringEndsWith('/circularity-hub/item/5', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function updateItemSendsPatch(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->updateItem(5, ['name' => 'Updated Widget']);

        $req = $this->history[0]['request'];
        $this->assertSame('PATCH', $req->getMethod());
        $this->assertStringEndsWith('/circularity-hub/item/5', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('Updated Widget', $body['name']);
    }

    #[Test]
    public function deleteItemSendsDelete(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->deleteItem(5);

        $req = $this->history[0]['request'];
        $this->assertSame('DELETE', $req->getMethod());
        $this->assertStringEndsWith('/circularity-hub/item/5', (string) $req->getUri());
    }

    #[Test]
    public function listOrdersReturnsTypedArray(): void
    {
        $data = ['items' => [$this->sampleOrderData()]];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->listOrders();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(CircularityHubOrder::class, $result[0]);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame('ORD-001', $result[0]->orderNumber);
        $this->assertFalse($result[0]->completed);
        $this->assertFalse($result[0]->cancelled);
        $this->assertNotNull($result[0]->billingData);
        $this->assertSame('John', $result[0]->billingData->firstName);

        $this->assertStringEndsWith('/circularity-hub/orders', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function createOrderReturnsIntFromHeader(): void
    {
        $service = $this->createService([
            new GuzzleResponse(201, ['Location-Id' => '42'], ''),
        ]);

        $id = $service->createOrder([1, 2, 3]);

        $this->assertSame(42, $id);

        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringEndsWith('/circularity-hub/orders', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame([1, 2, 3], $body);
    }

    #[Test]
    public function getOrderReturnsTypedModel(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($this->sampleOrderData()))]);

        $result = $service->getOrder(1);

        $this->assertInstanceOf(CircularityHubOrder::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('ORD-001', $result->orderNumber);
        $this->assertSame('2024-03-01T00:00:00Z', $result->createdAt);
        $this->assertSame(42, $result->userId);
        $this->assertSame(99.99, $result->totalPrice);
        $this->assertFalse($result->completed);
        $this->assertFalse($result->cancelled);
        $this->assertNull($result->cancellationReason);
        $this->assertNotNull($result->billingData);
        $this->assertSame('Doe', $result->billingData->lastName);
        $this->assertSame('Main St', $result->billingData->street);
        $this->assertSame('42', $result->billingData->houseNumber);
        $this->assertSame('12345', $result->billingData->zipCode);
        $this->assertSame('Berlin', $result->billingData->city);
        $this->assertCount(1, $result->articles);

        $this->assertStringEndsWith('/circularity-hub/order/1', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function updateOrderSendsPatch(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->updateOrder(1, ['completed' => true]);

        $req = $this->history[0]['request'];
        $this->assertSame('PATCH', $req->getMethod());
        $this->assertStringEndsWith('/circularity-hub/order/1', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertTrue($body['completed']);
    }
}
