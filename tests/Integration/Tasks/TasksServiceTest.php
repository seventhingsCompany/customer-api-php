<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration\Tasks;

use Seventhings\Models\CreateTaskRequest;
use Seventhings\Models\Enums\TaskReferenceType;
use Seventhings\Models\Enums\TaskStatus;
use Seventhings\Models\Enums\TimeIntervalUnit;
use Seventhings\Models\TaskListOptions;
use Seventhings\Models\TaskReferenceInput;
use Seventhings\Models\TimeInterval;
use Seventhings\Models\UpdateTaskRequest;
use Seventhings\Tests\Integration\IntegrationTestCase;

final class TasksServiceTest extends IntegrationTestCase
{
    /** @var string[] */
    private array $taskCleanup = [];
    /** @var string[] */
    private array $objectCleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->taskCleanup as $uuid) {
            try {
                self::$client->tasks->delete($uuid);
            } catch (\Throwable) {
            }
        }
        foreach ($this->objectCleanup as $uuid) {
            try {
                self::$client->objects->delete($uuid);
            } catch (\Throwable) {
            }
        }
    }

    private function createRefObject(): string
    {
        $uuid = self::$client->objects->create([
            'inventory_name' => 'PHP SDK Task Ref ' . $this->uniqueSuffix(),
            'barcode' => 'PHP-TASK-REF-' . $this->uniqueSuffix(),
        ]);
        $this->objectCleanup[] = $uuid;
        return $uuid;
    }

    private function currentUserUuid(): string
    {
        $users = self::$client->users->list();
        return $users->items[0]->uuid;
    }

    private function createTask(string $title): string
    {
        $refUuid = $this->createRefObject();
        $userUuid = $this->currentUserUuid();

        return self::$client->tasks->create(new CreateTaskRequest(
            title: $title,
            deadline: '2026-12-31',
            assignees: [$userUuid],
            references: [
                new TaskReferenceInput(TaskReferenceType::Asset, $refUuid),
            ],
            reminders: [
                new TimeInterval(TimeIntervalUnit::Days, 1),
            ],
            recurringSchedule: null,
        ));
    }

    public function testTasksCRUD(): void
    {
        $title = 'PHP SDK Task ' . $this->uniqueSuffix();
        $uuid = $this->createTask($title);
        $this->taskCleanup[] = $uuid;
        $this->assertNotEmpty($uuid);

        $task = self::$client->tasks->get($uuid);
        $this->assertSame($uuid, $task->uuid);
        $this->assertSame($title, $task->title);

        $refUuid = $this->createRefObject();
        $updatedTitle = $title . ' Updated';
        self::$client->tasks->update($uuid, new UpdateTaskRequest(
            title: $updatedTitle,
            deadline: '2026-12-31',
            assignees: [$this->currentUserUuid()],
            references: [
                new TaskReferenceInput(TaskReferenceType::Asset, $refUuid),
            ],
            reminders: [
                new TimeInterval(TimeIntervalUnit::Days, 1),
            ],
            recurringSchedule: null,
        ));

        $task = self::$client->tasks->get($uuid);
        $this->assertSame($updatedTitle, $task->title);

        self::$client->tasks->delete($uuid);
        $this->taskCleanup = array_filter($this->taskCleanup, fn($id) => $id !== $uuid);
    }

    public function testTaskStatusTransition(): void
    {
        $title = 'PHP SDK Status Task ' . $this->uniqueSuffix();
        $uuid = $this->createTask($title);
        $this->taskCleanup[] = $uuid;

        self::$client->tasks->updateStatus($uuid, TaskStatus::Closed);

        $task = self::$client->tasks->get($uuid);
        $this->assertSame(TaskStatus::Closed, $task->status);
    }

    public function testTasksListWithFilters(): void
    {
        $title = 'PHP SDK List Task ' . $this->uniqueSuffix();
        $uuid = $this->createTask($title);
        $this->taskCleanup[] = $uuid;

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
