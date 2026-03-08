<?php

declare(strict_types=1);

namespace Seventhings\Tasks;

use Seventhings\Helpers;
use Seventhings\HttpClient;
use Seventhings\Models\CreateTaskRequest;
use Seventhings\Models\Enums\TaskStatus;
use Seventhings\Models\TaskListOptions;
use Seventhings\Models\TaskResponse;
use Seventhings\Models\UpdateTaskRequest;

final class TasksService
{
    public function __construct(private readonly HttpClient $httpClient) {}

    /**
     * @return TaskResponse[]
     */
    public function list(?TaskListOptions $options = null): array
    {
        $path = 'task-management/tasks';
        if ($options !== null) {
            $qs = $options->toQueryString();
            if ($qs !== '') {
                $path .= '?' . $qs;
            }
        }

        $response = $this->httpClient->get($path);

        return array_map(
            fn(array $item) => TaskResponse::fromArray($item),
            $response->json(),
        );
    }

    public function get(string $uuid): TaskResponse
    {
        $response = $this->httpClient->get('task-management/task/' . $uuid);

        return TaskResponse::fromArray($response->json());
    }

    public function create(CreateTaskRequest $request): string
    {
        $response = $this->httpClient->post('task-management/task', $request->toArray());

        return Helpers::uuidFromLocationHeader($response);
    }

    public function update(string $uuid, UpdateTaskRequest $request): void
    {
        $this->httpClient->put('task-management/task/' . $uuid, $request->toArray());
    }

    public function delete(string $uuid): void
    {
        $this->httpClient->delete('task-management/task/' . $uuid);
    }

    public function updateStatus(string $uuid, TaskStatus $status): void
    {
        $this->httpClient->put('task-management/task/' . $uuid . '/status', [
            'status' => $status->value,
        ]);
    }
}
