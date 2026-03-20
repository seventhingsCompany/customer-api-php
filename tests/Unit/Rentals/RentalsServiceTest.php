<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Rentals;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Models\CreateRentalCaseRequest;
use Seventhings\Models\Enums\RentalCaseReferenceType;
use Seventhings\Models\Enums\RentalCaseStatus;
use Seventhings\Models\Enums\RenterType;
use Seventhings\Models\Enums\TimeIntervalUnit;
use Seventhings\Models\ListOptions;
use Seventhings\Models\RentalCaseReferenceInput;
use Seventhings\Models\RentalCaseRenter;
use Seventhings\Models\RentalCaseResponse;
use Seventhings\Models\TimeInterval;
use Seventhings\Models\UpdateRentalCaseRequest;
use Seventhings\Rentals\RentalsService;

final class RentalsServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): RentalsService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new RentalsService($httpClient);
    }

    private function sampleRentalData(): array
    {
        return [
            'uuid' => 'r1',
            'status' => 'borrowed',
            'title' => 'Test Rental',
            'renter' => ['type' => 'plain', 'value' => 'John Doe'],
            'references' => [
                ['type' => 'asset', 'uuid' => 'a1', 'name' => 'Laptop', 'id' => 5],
            ],
            'issue_date' => '2024-03-01',
            'due_date' => '2024-03-15',
            'comment' => 'Handle with care',
            'issue_date_reminder' => ['unit' => 'days', 'value' => 3],
            'due_date_reminder' => ['unit' => 'days', 'value' => 1],
            'responsible_user_uuid' => 'user-uuid-1',
            'author' => 'admin@example.com',
            'attachments' => [
                ['uuid' => 'att1', 'name' => 'receipt.pdf', 'type' => 'application/pdf', 'size' => 4096, 'data_uri' => '/file/att1/data', 'thumbnail_uri' => '/file/att1/thumbnail'],
            ],
            'created_at' => '2024-02-28T00:00:00Z',
            'updated_at' => '2024-03-01T00:00:00Z',
        ];
    }

    #[Test]
    public function listReturnsRentalCaseResponseArray(): void
    {
        $data = ['items' => [$this->sampleRentalData()]];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->list();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(RentalCaseResponse::class, $result[0]);
        $this->assertSame('r1', $result[0]->uuid);
        $this->assertSame(RentalCaseStatus::Borrowed, $result[0]->status);
        $this->assertSame('Test Rental', $result[0]->title);
        $this->assertInstanceOf(RentalCaseRenter::class, $result[0]->renter);
        $this->assertSame(RenterType::Plain, $result[0]->renter->type);
        $this->assertSame('John Doe', $result[0]->renter->value);
        $this->assertCount(1, $result[0]->references);
        $this->assertSame(RentalCaseReferenceType::Asset, $result[0]->references[0]->type);
        $this->assertSame('2024-03-01', $result[0]->issueDate);
        $this->assertSame('2024-03-15', $result[0]->dueDate);
        $this->assertNotNull($result[0]->issueDateReminder);
        $this->assertSame(TimeIntervalUnit::Days, $result[0]->issueDateReminder->unit);
        $this->assertNotNull($result[0]->dueDateReminder);
        $this->assertSame('user-uuid-1', $result[0]->responsibleUserUuid);
        $this->assertSame('admin@example.com', $result[0]->author);
        $this->assertCount(1, $result[0]->attachments);

        $this->assertStringEndsWith('/rental-management/rental-cases', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function listWithOptionsAppendsQueryString(): void
    {
        $data = ['items' => []];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $options = new ListOptions(page: 2, perPage: 5);
        $service->list($options);

        $uri = (string) $this->history[0]['request']->getUri();
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('per_page=5', $uri);
    }

    #[Test]
    public function getReturnsRentalCaseResponse(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($this->sampleRentalData()))]);

        $result = $service->get('r1');

        $this->assertInstanceOf(RentalCaseResponse::class, $result);
        $this->assertSame('r1', $result->uuid);
        $this->assertInstanceOf(RentalCaseRenter::class, $result->renter);
        $this->assertSame('John Doe', $result->renter->value);
        $this->assertStringEndsWith('/rental-management/rental-case/r1', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function createReturnsUuid(): void
    {
        $service = $this->createService([
            new GuzzleResponse(201, ['Location' => '/customer-api/v1/rental-management/rental-case/new-uuid'], ''),
        ]);

        $request = new CreateRentalCaseRequest(
            title: 'Test Rental',
            issueDate: '2024-04-01',
            dueDate: '2024-04-15',
            comment: 'Test rental',
            responsibleUserUuid: 'user-uuid-1',
            renter: new RentalCaseRenter(RenterType::Plain, 'Jane Doe'),
            references: [new RentalCaseReferenceInput(RentalCaseReferenceType::Asset, 'a1')],
            attachments: [],
        );

        $uuid = $service->create($request);

        $this->assertSame('new-uuid', $uuid);

        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringEndsWith('/rental-management/rental-case', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame(['type' => 'plain', 'value' => 'Jane Doe'], $body['renter']);
        $this->assertSame([['type' => 'asset', 'uuid' => 'a1']], $body['references']);
        $this->assertSame('2024-04-01', $body['issue_date']);
    }

    #[Test]
    public function updateSendsPut(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $request = new UpdateRentalCaseRequest(
            title: 'Updated Rental',
            issueDate: '2024-04-01',
            dueDate: '2024-04-15',
            comment: 'Updated comment',
            responsibleUserUuid: 'user-uuid-1',
            renter: new RentalCaseRenter(RenterType::Plain, 'Jane Doe'),
            references: [],
            attachments: [],
        );

        $service->update('r1', $request);

        $req = $this->history[0]['request'];
        $this->assertSame('PUT', $req->getMethod());
        $this->assertStringEndsWith('/rental-management/rental-case/r1', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('Updated comment', $body['comment']);
    }

    #[Test]
    public function deleteReturnsVoid(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->delete('r1');

        $req = $this->history[0]['request'];
        $this->assertSame('DELETE', $req->getMethod());
        $this->assertStringEndsWith('/rental-management/rental-case/r1', (string) $req->getUri());
    }
}
