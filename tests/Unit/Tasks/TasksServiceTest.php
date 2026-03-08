<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Tasks;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\HttpClient;
use Seventhings\Models\CreateTaskRequest;
use Seventhings\Models\Enums\TaskReferenceStatus;
use Seventhings\Models\Enums\TaskReferenceType;
use Seventhings\Models\Enums\TaskStatus;
use Seventhings\Models\Enums\TimeIntervalUnit;
use Seventhings\Models\TaskListOptions;
use Seventhings\Models\TaskReferenceInput;
use Seventhings\Models\TaskResponse;
use Seventhings\Models\TimeInterval;
use Seventhings\Models\UpdateTaskRequest;
use Seventhings\Tasks\TasksService;

final class TasksServiceTest extends TestCase
{
    private array $history = [];

    private function createService(array $responses): TasksService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);
        $httpClient = new HttpClient('https://example.com', $guzzle);
        $httpClient->setToken('tok');

        return new TasksService($httpClient);
    }

    private function sampleTaskData(): array
    {
        return [
            'uuid' => 't1',
            'title' => 'Fix bug',
            'status' => 'open',
            'deadline' => '2024-12-31',
            'assignees' => ['user-1', 'user-2'],
            'author' => 'author-1',
            'references' => [
                ['type' => 'asset', 'uuid' => 'a1', 'name' => 'Asset 1', 'id' => 10, 'status' => 'open'],
            ],
            'reminders' => [
                ['unit' => 'days', 'value' => 3],
            ],
            'recurring_schedule' => ['unit' => 'weeks', 'value' => 1],
            'comment' => 'Please fix ASAP',
            'attachments' => [
                ['uuid' => 'att1', 'name' => 'log.txt', 'type' => 'text/plain', 'size' => 512, 'data_uri' => '/file/att1/data', 'thumbnail_uri' => '/file/att1/thumbnail'],
            ],
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-02T00:00:00Z',
        ];
    }

    #[Test]
    public function listReturnsTaskResponseArray(): void
    {
        $data = [$this->sampleTaskData()];
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($data))]);

        $result = $service->list();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TaskResponse::class, $result[0]);
        $this->assertSame('t1', $result[0]->uuid);
        $this->assertSame('Fix bug', $result[0]->title);
        $this->assertSame(TaskStatus::Open, $result[0]->status);
        $this->assertSame('2024-12-31', $result[0]->deadline);
        $this->assertSame(['user-1', 'user-2'], $result[0]->assignees);
        $this->assertSame('author-1', $result[0]->author);
        $this->assertCount(1, $result[0]->references);
        $this->assertSame(TaskReferenceType::Asset, $result[0]->references[0]->type);
        $this->assertSame(TaskReferenceStatus::Open, $result[0]->references[0]->status);
        $this->assertCount(1, $result[0]->reminders);
        $this->assertSame(TimeIntervalUnit::Days, $result[0]->reminders[0]->unit);
        $this->assertSame(3, $result[0]->reminders[0]->value);
        $this->assertNotNull($result[0]->recurringSchedule);
        $this->assertSame(TimeIntervalUnit::Weeks, $result[0]->recurringSchedule->unit);
        $this->assertSame('Please fix ASAP', $result[0]->comment);
        $this->assertCount(1, $result[0]->attachments);
        $this->assertSame('att1', $result[0]->attachments[0]->uuid);

        $this->assertStringEndsWith('/task-management/tasks', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function listWithOptionsAppendsQueryString(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], '[]')]);

        $options = new TaskListOptions(
            status: TaskStatus::Open,
            deadlineFrom: '2024-01-01',
            deadlineTo: '2024-12-31',
            assignee: 'user-1',
            author: 'author-1',
            referenceType: TaskReferenceType::Asset,
        );
        $service->list($options);

        $uri = (string) $this->history[0]['request']->getUri();
        $this->assertStringContainsString('status=open', $uri);
        $this->assertStringContainsString('deadline_from=2024-01-01', $uri);
        $this->assertStringContainsString('deadline_to=2024-12-31', $uri);
        $this->assertStringContainsString('assignee=user-1', $uri);
        $this->assertStringContainsString('author=author-1', $uri);
        $this->assertStringContainsString('reference_type=asset', $uri);
    }

    #[Test]
    public function getReturnsTaskResponse(): void
    {
        $service = $this->createService([new GuzzleResponse(200, [], json_encode($this->sampleTaskData()))]);

        $result = $service->get('t1');

        $this->assertInstanceOf(TaskResponse::class, $result);
        $this->assertSame('t1', $result->uuid);
        $this->assertStringEndsWith('/task-management/task/t1', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function createReturnsUuid(): void
    {
        $service = $this->createService([
            new GuzzleResponse(201, ['Location' => '/customer-api/v1/task-management/task/new-uuid'], ''),
        ]);

        $request = new CreateTaskRequest(
            title: 'New task',
            deadline: '2024-06-30',
            assignees: ['user-1'],
            references: [new TaskReferenceInput(TaskReferenceType::Asset, 'a1')],
            reminders: [new TimeInterval(TimeIntervalUnit::Days, 1)],
            recurringSchedule: null,
            comment: 'A comment',
            notify: true,
        );

        $uuid = $service->create($request);

        $this->assertSame('new-uuid', $uuid);

        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringEndsWith('/task-management/task', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('New task', $body['title']);
        $this->assertSame([['type' => 'asset', 'uuid' => 'a1']], $body['references']);
        $this->assertSame([['unit' => 'days', 'value' => 1]], $body['reminders']);
        $this->assertSame('A comment', $body['comment']);
        $this->assertTrue($body['notify']);
    }

    #[Test]
    public function updateSendsPut(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $request = new UpdateTaskRequest(
            title: 'Updated task',
            deadline: null,
            assignees: [],
            references: [],
            reminders: [],
            recurringSchedule: null,
        );

        $service->update('t1', $request);

        $req = $this->history[0]['request'];
        $this->assertSame('PUT', $req->getMethod());
        $this->assertStringEndsWith('/task-management/task/t1', (string) $req->getUri());
    }

    #[Test]
    public function deleteReturnsVoid(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->delete('t1');

        $req = $this->history[0]['request'];
        $this->assertSame('DELETE', $req->getMethod());
        $this->assertStringEndsWith('/task-management/task/t1', (string) $req->getUri());
    }

    #[Test]
    public function updateStatusSendsPutWithBody(): void
    {
        $service = $this->createService([new GuzzleResponse(204, [], '')]);

        $service->updateStatus('t1', TaskStatus::Closed);

        $req = $this->history[0]['request'];
        $this->assertSame('PUT', $req->getMethod());
        $this->assertStringEndsWith('/task-management/task/t1/status', (string) $req->getUri());

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame(['status' => 'closed'], $body);
    }
}
