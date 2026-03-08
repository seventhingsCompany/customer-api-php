<?php

/**
 * Demo — exercises the core seventhings PHP SDK modules (Auth, Objects,
 * Files, Tasks) against a real instance. Configure via environment variables:
 *
 *   SEVENTHINGS_BASE_URL   — e.g. https://example.seventhings.com
 *   SEVENTHINGS_USERNAME   — login username
 *   SEVENTHINGS_PASSWORD   — login password
 *   SEVENTHINGS_CLIENT_ID  — OAuth client ID
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Seventhings\Client;
use Seventhings\Models\ApiException;
use Seventhings\Models\CreateTaskRequest;
use Seventhings\Models\Enums\FilterOperator;
use Seventhings\Models\Enums\SortDirection;
use Seventhings\Models\Enums\TaskStatus;
use Seventhings\Models\Enums\TimeIntervalUnit;
use Seventhings\Models\FileAttachment;
use Seventhings\Models\FilterEntry;
use Seventhings\Models\ListOptions;
use Seventhings\Models\TaskReferenceInput;
use Seventhings\Models\Enums\TaskReferenceType;
use Seventhings\Models\TimeInterval;

// ── Helpers ──────────────────────────────────────────────────────────────────

function requireEnv(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        fprintf(STDERR, "Missing required environment variable: %s\n", $key);
        exit(1);
    }
    return $value;
}

function section(string $tag, string $msg): void
{
    printf("\n── %s ──────────────────────────────────────────\n", $tag);
    pf($tag, $msg);
}

function pf(string $tag, string $format, mixed ...$args): void
{
    printf("[%-7s] " . $format . "\n", $tag, ...$args);
}

function isNotFound(\Throwable $e): bool
{
    return $e instanceof ApiException && $e->statusCode === 404;
}

// ── Configuration ────────────────────────────────────────────────────────────

$baseUrl  = requireEnv('SEVENTHINGS_BASE_URL');
$username = requireEnv('SEVENTHINGS_USERNAME');
$password = requireEnv('SEVENTHINGS_PASSWORD');
$clientId = requireEnv('SEVENTHINGS_CLIENT_ID');

// ── Auth ─────────────────────────────────────────────────────────────────────

section('Auth', 'Logging in…');

$client = Client::withToken($baseUrl, 'unused');
$tok = $client->auth->login($username, $password, $clientId);
$client->setToken($tok->accessToken);

$truncated = substr($tok->accessToken, 0, 20) . '…';
pf('Auth', 'Logged in — user_id=%d, token=%s', $tok->userId, $truncated);

// ── Objects ──────────────────────────────────────────────────────────────────

section('Objects', 'Listing objects…');

$objs = $client->objects->list(new ListOptions(page: 1, perPage: 5));
pf('Objects', 'Listed %d object(s) (first page, max 5)', count($objs));

// Create
$ts = intval(microtime(true) * 1000);
$objUuid = $client->objects->create([
    'inventory_name' => 'SDK Demo Object',
    'barcode'        => sprintf('SDK-DEMO-%d', $ts),
]);
pf('Objects', 'Created object %s', $objUuid);

// Patch
$client->objects->patch($objUuid, ['inventory_name' => 'SDK Demo Object (updated)']);
$updated = $client->objects->get($objUuid);
pf('Objects', 'Patched object — inventory_name=%s', $updated['inventory_name']);

// Archive / Unarchive
$client->objects->archive($objUuid);
pf('Objects', 'Archived object %s', $objUuid);

$client->objects->unarchive($objUuid);
pf('Objects', 'Unarchived object %s', $objUuid);

// Delete + confirm 404
$client->objects->delete($objUuid);
pf('Objects', 'Deleted object %s', $objUuid);

try {
    $client->objects->get($objUuid);
    fprintf(STDERR, "[Objects] Expected 404 after deletion\n");
    exit(1);
} catch (\Throwable $e) {
    if (isNotFound($e)) {
        pf('Objects', 'Confirmed deletion (404)');
    } else {
        fprintf(STDERR, "[Objects] Expected 404 after deletion, got: %s\n", $e->getMessage());
        exit(1);
    }
}

// ── Filtered listing ─────────────────────────────────────────────────────────

section('Objects', 'Fetching last 5 changed assets (sorted + filtered)…');

$recentObjs = $client->objects->list(new ListOptions(
    page: 1,
    perPage: 5,
    sort: ['updated_at' => SortDirection::Desc],
));
pf('Objects', 'Got %d recently changed asset(s):', count($recentObjs));
foreach (array_values($recentObjs) as $i => $obj) {
    $name = $obj['inventory_name'] ?? '';
    $updatedAt = $obj['updated_at'] ?? '';
    pf('Objects', '  %d. %s (updated_at=%s)', $i + 1, $name, $updatedAt);
}

// Filter by name — find objects whose inventory_name contains "SDK"
section('Objects', 'Filtering assets by name containing "SDK"…');

$filtered = $client->objects->list(new ListOptions(
    page: 1,
    perPage: 5,
    filters: [
        new FilterEntry('inventory_name', FilterOperator::Like, ['SDK']),
    ],
));
pf('Objects', 'Got %d asset(s) matching filter:', count($filtered));
foreach (array_values($filtered) as $i => $obj) {
    $name = $obj['inventory_name'] ?? '';
    pf('Objects', '  %d. %s', $i + 1, $name);
}

// ── Files ────────────────────────────────────────────────────────────────────

section('Files', 'Uploading file…');

$fileContent = "Hello from the seventhings PHP SDK demo!\n";
$fileUuid = $client->files->upload('demo.txt', $fileContent);
pf('Files', 'Uploaded file %s (demo.txt, %d bytes)', $fileUuid, strlen($fileContent));

$fileMeta = $client->files->get($fileUuid);
pf('Files', 'File metadata — name=%s, type=%s, size=%d', $fileMeta->name, $fileMeta->type, $fileMeta->size);

// Create a temporary object to attach the file to
$tmpObjUuid = $client->objects->create([
    'inventory_name' => 'SDK Demo File Host',
    'barcode'        => sprintf('SDK-FILE-%d', $ts),
]);
pf('Files', 'Created temp object %s for file attachment', $tmpObjUuid);

$attachment = [new FileAttachment('documents', $fileUuid)];

$client->objects->addFiles($tmpObjUuid, $attachment);
pf('Files', 'Attached file %s to object %s', $fileUuid, $tmpObjUuid);

// Clean up: remove file from object, then delete the object
$client->objects->removeFiles($tmpObjUuid, $attachment);
pf('Files', 'Removed file from object %s', $tmpObjUuid);

$client->objects->delete($tmpObjUuid);
pf('Files', 'Deleted temp object %s', $tmpObjUuid);

// ── Tasks ────────────────────────────────────────────────────────────────────

section('Tasks', 'Creating task…');

// Look up current user UUID for task assignee
$currentUser = $client->users->getById($tok->userId);
pf('Tasks', 'Current user UUID: %s', $currentUser->uuid);

// Create a temporary object for the task reference
$taskObjUuid = $client->objects->create([
    'inventory_name' => 'SDK Demo Task Target',
    'barcode'        => sprintf('SDK-TASK-%d', $ts),
]);
pf('Tasks', 'Created reference object %s', $taskObjUuid);

$taskUuid = $client->tasks->create(new CreateTaskRequest(
    title: 'SDK Demo Task',
    deadline: '2026-12-31',
    assignees: [$currentUser->uuid],
    references: [
        new TaskReferenceInput(TaskReferenceType::Asset, $taskObjUuid),
    ],
    reminders: [
        new TimeInterval(TimeIntervalUnit::Days, 1),
    ],
    recurringSchedule: null,
));
pf('Tasks', 'Created task %s referencing object %s', $taskUuid, $taskObjUuid);

// Close the task
$client->tasks->updateStatus($taskUuid, TaskStatus::Closed);
pf('Tasks', 'Updated task status to closed');

// Delete the task + confirm 404
$client->tasks->delete($taskUuid);
pf('Tasks', 'Deleted task %s', $taskUuid);

try {
    $client->tasks->get($taskUuid);
    fprintf(STDERR, "[Tasks  ] Expected 404 after deletion\n");
    exit(1);
} catch (\Throwable $e) {
    if (isNotFound($e)) {
        pf('Tasks', 'Confirmed deletion (404)');
    } else {
        fprintf(STDERR, "[Tasks  ] Expected 404 after deletion, got: %s\n", $e->getMessage());
        exit(1);
    }
}

// Clean up reference object
$client->objects->delete($taskObjUuid);
pf('Tasks', 'Deleted reference object %s', $taskObjUuid);

// ── Auth cleanup ─────────────────────────────────────────────────────────────

section('Auth', 'Revoking tokens…');

$client->auth->revokeTokens();
pf('Auth', 'Tokens revoked');

echo "\nDone — all steps completed successfully.\n";
