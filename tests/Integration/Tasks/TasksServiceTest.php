<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Tasks;

use Seventhings\Models\CreateTaskRequest;
use Seventhings\Models\Enums\TaskStatus;
use Seventhings\Models\TaskListOptions;
use Seventhings\Models\UpdateTaskRequest;
use Seventhings\Tests\Integration\IntegrationTestCase;

final class TasksServiceTest extends IntegrationTestCase
{
    /** @var string[] */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $uuid) {
            try {
                self::$client->tasks->delete($uuid);
            } catch (\Throwable) {
            }
        }
    }

    public function testTasksCRUD(): void
    {
        $title = 'PHP SDK Task ' . $this->uniqueSuffix();
        $request = new CreateTaskRequest(title: $title);
        $uuid = self::$client->tasks->create($request);
        $this->cleanup[] = $uuid;
        $this->assertNotEmpty($uuid);

        $task = self::$client->tasks->get($uuid);
        $this->assertSame($uuid, $task->uuid);
        $this->assertSame($title, $task->title);

        $updatedTitle = $title . ' Updated';
        self::$client->tasks->update($uuid, new UpdateTaskRequest(title: $updatedTitle));

        $task = self::$client->tasks->get($uuid);
        $this->assertSame($updatedTitle, $task->title);

        self::$client->tasks->delete($uuid);
        $this->cleanup = array_filter($this->cleanup, fn($id) => $id !== $uuid);
    }

    public function testTaskStatusTransition(): void
    {
        $title = 'PHP SDK Status Task ' . $this->uniqueSuffix();
        $request = new CreateTaskRequest(title: $title);
        $uuid = self::$client->tasks->create($request);
        $this->cleanup[] = $uuid;

        self::$client->tasks->updateStatus($uuid, TaskStatus::Closed);

        $task = self::$client->tasks->get($uuid);
        $this->assertSame(TaskStatus::Closed, $task->status);
    }

    public function testTasksListWithFilters(): void
    {
        $title = 'PHP SDK List Task ' . $this->uniqueSuffix();
        $request = new CreateTaskRequest(title: $title);
        $uuid = self::$client->tasks->create($request);
        $this->cleanup[] = $uuid;

        $options = new TaskListOptions(status: TaskStatus::Open);
        $list = self::$client->tasks->list($options);

        $found = false;
        foreach ($list as $task) {
            if ($task->uuid === $uuid) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Created task should appear in filtered list');
    }
}
