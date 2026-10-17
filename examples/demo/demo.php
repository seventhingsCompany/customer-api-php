<?php

/**
 * Demo — exercises the core seventhings PHP SDK modules (Auth, Objects,
 * Files, Tasks, Persons, History, Reports, Catalog, Rentals) against a real instance. Configure via environment variables:
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
use Seventhings\Models\CreateRentalCaseRequest;
use Seventhings\Models\CreateReportRequest;
use Seventhings\Models\CreateTaskRequest;
use Seventhings\Models\Enums\FilterOperator;
use Seventhings\Models\Enums\RentalCaseReferenceType;
use Seventhings\Models\Enums\RenterType;
use Seventhings\Models\Enums\SortDirection;
use Seventhings\Models\Enums\TaskStatus;
use Seventhings\Models\Enums\TimeIntervalUnit;
use Seventhings\Models\FileAttachment;
use Seventhings\Models\FilterEntry;
use Seventhings\Models\HistoryListOptions;
use Seventhings\Models\ListOptions;
use Seventhings\Models\PersonListOptions;
use Seventhings\Models\RentalCaseReferenceInput;
use Seventhings\Models\RentalCaseRenter;
use Seventhings\Models\TaskReferenceInput;
use Seventhings\Models\Enums\TaskReferenceType;
use Seventhings\Models\TimeInterval;
use Seventhings\Models\UpdateRentalCaseRequest;

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

$byBarcode = $client->objects->getByBarcode(sprintf('SDK-DEMO-%d', $ts));
pf('Objects', 'Barcode lookup → %s', $byBarcode['asset_uuid'] ?? $byBarcode['uuid'] ?? '');

// Patch
$client->objects->patch($objUuid, ['inventory_name' => 'SDK Demo Object (updated)']);
$updated = $client->objects->get($objUuid);
pf('Objects', 'Patched object — inventory_name=%s', $updated['inventory_name']);

$historyOptions = new HistoryListOptions(page: 1, perPage: 5);
$objectHistory = $client->objects->history($objUuid, $historyOptions);
pf('History', 'Object: %d total change(s), %d on this page', $objectHistory->total, count($objectHistory->items));

// Render the object before deleting it. The API returns PDF bytes without
// storing a document; this demo only prints the size.
$templates = $client->reports->listTemplates();
pf('Reports', 'Found %d PDF template(s)', count($templates));
if ($templates !== []) {
    $pdf = $client->reports->create(new CreateReportRequest($templates[0]->uuid, [$objUuid]));
    pf('Reports', 'Rendered "%s": %d PDF bytes', $templates[0]->name, strlen($pdf));
}

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

$taskHistory = $client->tasks->history($taskUuid, $historyOptions);
pf('History', 'Task: %d total change(s)', $taskHistory->total);

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

// ── Persons ──────────────────────────────────────────────────────────────────
// Field keys are template-defined; the default person template uses
// first_name / last_name. To turn a person into a platform user, use
// createUser() with a filter that matches exactly one person:
//
//   $client->persons->createUser(new FilterObject(
//       filter: ['email' => [FilterOperator::Eq->value => $email]],
//   ));

section('Persons', 'Listing persons…');

$personResp = $client->persons->list(new PersonListOptions(
    page: 1,
    perPage: 5,
    sort: ['id' => SortDirection::Asc],
));
pf('Persons', 'Got %d person(s) (page 1, max 5):', count($personResp->items));
foreach ($personResp->items as $i => $p) {
    pf(
        'Persons',
        '  %d. id=%d uuid=%s %s %s <%s>',
        $i + 1,
        $p->id,
        $p->uuid,
        $p->firstname ?? '',
        $p->lastname ?? '',
        $p->email,
    );
}

if (!empty($personResp->items)) {
    $first = $personResp->items[0];
    $byUuid = $client->persons->get($first->uuid);
    pf('Persons', 'persons->get(%s) → id=%d email=%s', $first->uuid, $byUuid->id, $byUuid->email);

    $byId = $client->persons->getById($first->id);
    pf('Persons', 'persons->getById(%d) → uuid=%s email=%s', $first->id, $byId->uuid, $byId->email);
}

pf('Persons', 'Total persons: %d', $client->persons->count());

// Full lifecycle: create → patch → delete.
$personEmail = sprintf('demo.person+%d@example.com', (int) (microtime(true) * 1000));
$personUuid = $client->persons->create([
    'email' => $personEmail,
    'first_name' => 'Demo',
    'last_name' => 'Person',
]);
pf('Persons', 'Created person %s <%s>', $personUuid, $personEmail);

// PATCH returns an empty body, so re-fetch to read the updated values.
$client->persons->patch($personUuid, ['last_name' => 'Patched']);
$patched = $client->persons->get($personUuid);
pf('Persons', 'Patched last_name → %s', $patched->lastname ?? '');

$personHistory = $client->persons->history($personUuid, $historyOptions);
foreach ($personHistory->items as $event) {
    pf('History', 'Person: %s — %s', $event->occurredAt, $event->eventName);
}

$client->persons->delete($personUuid);
pf('Persons', 'Deleted person %s', $personUuid);

try {
    $client->persons->get($personUuid);
    fprintf(STDERR, "[Persons] Expected 404 after deletion, but get() succeeded\n");
    exit(1);
} catch (ApiException $e) {
    if ($e->statusCode !== 404) {
        fprintf(STDERR, "[Persons] Expected 404 after deletion, got: %s\n", $e->getMessage());
        exit(1);
    }
    pf('Persons', 'Confirmed 404 after deletion');
}

// ── History of existing resources ────────────────────────────────────────────

section('History', 'Reading room, location, and rental history…');
foreach (['rooms' => 'room_uuid', 'locations' => 'location_uuid', 'rentals' => null] as $module => $uuidKey) {
    $service = $client->$module;
    try {
        $items = $service->list(new ListOptions(perPage: 1));
    } catch (ApiException $e) {
        if ($module === 'rentals' && $e->isFeatureInactive()) {
            pf('History', 'Rentals module is not active; skipping');
            continue;
        }
        throw $e;
    }
    if ($items === []) {
        pf('History', 'No %s available; skipping', $module);
        continue;
    }
    $uuid = $uuidKey === null ? $items[0]->uuid : ($items[0][$uuidKey] ?? $items[0]['uuid']);
    $history = $service->history($uuid, $historyOptions);
    pf('History', '%s: %d total change(s)', $module, $history->total);
}

// ── Catalog items ────────────────────────────────────────────────────────────

section('Catalog', 'Creating a temporary catalog item…');
$catalogName = sprintf('SDK Demo Catalog %d', $ts);
$catalogUuid = null;
try {
    $catalogUuid = $client->catalogItems->create(['catalog_object_inventory_name' => $catalogName]);
} catch (ApiException $e) {
    if (!$e->isForbidden()) {
        throw $e;
    }
    pf('Catalog', 'Skipped: product catalog feature or create permission unavailable');
}
if ($catalogUuid !== null) {
    try {
        // PUT replaces the entire state: send the read field map back, not a patch.
        $item = $client->catalogItems->get($catalogUuid);
        $client->catalogItems->update($catalogUuid, ['catalog_object_inventory_name' => $catalogName . ' updated'] + $item->fields->data);
        $page = $client->catalogItems->list(new ListOptions(page: 1, perPage: 5, filters: [
            FilterEntry::eq('catalog_object_inventory_name', $catalogName . ' updated'),
        ]));
        if (count($page->items) !== 1 || $page->items[0]->uuid !== $catalogUuid) {
            throw new \RuntimeException('catalog filtered list did not return the temporary item');
        }
        pf('Catalog', 'Verified create, get, PUT and filtered list — total=%d', $page->total);
    } finally {
        $client->catalogItems->delete($catalogUuid);
    }
    try {
        $client->catalogItems->get($catalogUuid);
        fprintf(STDERR, "[Catalog] Expected 404 after deletion\n");
        exit(1);
    } catch (\Throwable $e) {
        if (!isNotFound($e)) {
            throw $e;
        }
        pf('Catalog', 'Deleted temporary item; confirmed 404');
    }
}

// ── Timed rental ─────────────────────────────────────────────────────────────

section('Rentals', 'Creating and updating a timed rental…');
$rentalObjUuid = $client->objects->create([
    'inventory_name' => 'SDK Demo Rental Target',
    'barcode'        => sprintf('SDK-RENTAL-%d', $ts),
]);
try {
    // A future date with a non-UTC offset demonstrates UTC normalization.
    $start = (new \DateTimeImmutable('+1 year', new \DateTimeZone('+02:00')))->setTime(10, 0);
    $date = $start->format('Y-m-d');
    $rentalArgs = [
        'issueDate' => $date,
        'dueDate' => $date,
        'comment' => '',
        'responsibleUserUuid' => $currentUser->uuid,
        'renter' => new RentalCaseRenter(RenterType::Plain, 'SDK Demo'),
        'references' => [new RentalCaseReferenceInput(RentalCaseReferenceType::Asset, $rentalObjUuid)],
        'attachments' => [],
        'isFullDay' => false,
        'issueAt' => $start->format(\DateTimeInterface::ATOM),
    ];
    $rentalUuid = null;
    try {
        $rentalUuid = $client->rentals->create(new CreateRentalCaseRequest(
            ...$rentalArgs,
            title: 'SDK Demo Timed Rental',
            dueAt: $start->modify('+2 hours')->format(\DateTimeInterface::ATOM),
        ));
    } catch (ApiException $e) {
        if (!$e->isFeatureInactive()) {
            throw $e;
        }
        pf('Rentals', 'Rentals module is not active; skipping');
    }
    if ($rentalUuid !== null) {
        try {
            $rental = $client->rentals->get($rentalUuid);
            pf('Rentals', 'Created timed rental — issue_at=%s, due_at=%s', $rental->issueAt, $rental->dueAt);
            // PUT replaces the full state, so resend every field.
            $client->rentals->update($rentalUuid, new UpdateRentalCaseRequest(
                ...$rentalArgs,
                title: 'SDK Demo Timed Rental updated',
                dueAt: $start->modify('+3 hours')->format(\DateTimeInterface::ATOM),
            ));
            $rental = $client->rentals->get($rentalUuid);
            pf('Rentals', 'Updated timed rental — due_at=%s', $rental->dueAt);
        } finally {
            $client->rentals->delete($rentalUuid);
            pf('Rentals', 'Deleted rental %s', $rentalUuid);
        }
    }
} finally {
    $client->objects->delete($rentalObjUuid);
}

// ── Auth cleanup ─────────────────────────────────────────────────────────────

section('Auth', 'Revoking tokens…');

$client->auth->revokeTokens();
pf('Auth', 'Tokens revoked');

echo "\nDone — all steps completed successfully.\n";
