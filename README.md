# seventhings PHP SDK

PHP SDK for the [seventhings](https://www.7things.de) asset tracking API.

## Installation

Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:SeventhingsCompany/customer-api-php.git"
        }
    ]
}
```

Then install:

```bash
composer require seventhings/customer-api-php
```

**Requirements:** PHP ^8.5, Guzzle 7 (only runtime dependency).

## Quick Start

```php
use Seventhings\Client;

// Authenticate with credentials (recommended)
$client = Client::withCredentials($baseUrl, $username, $password, $clientId);

// Or use an existing token
$client = Client::withToken($baseUrl, $accessToken);
```

## Usage by Module

### Auth

```php
// Health check (unauthenticated)
$client->auth->ping();

// Login
$token = $client->auth->login($username, $password, $clientId);

// Refresh token (uses stored clientId from login)
$refreshed = $client->auth->refresh($token->refreshToken);

// Revoke all tokens
$client->auth->revokeTokens();
```

### Objects

```php
use Seventhings\Models\ListOptions;
use Seventhings\Models\FilterEntry;
use Seventhings\Models\Enums\FilterOperator;
use Seventhings\Models\FileAttachment;

// List objects (one page of raw arrays)
$objects = $client->objects->list();

// Iterate every object across all pages (lazy; default page size 100).
// Each item is a type-safe Fields wrapper over the instance-defined body.
foreach ($client->objects->all() as $obj) {
    echo $obj->uuid(), ' ', $obj->string('inventory_name'), "\n";
    $price = $obj->float('purchasing_price');   // null if absent or non-numeric
}

// Create (field names are instance-specific)
$uuid = $client->objects->create([
    'inventory_name' => 'Laptop #42',
    'barcode' => 'INV-0042',
]);

// Get, patch, delete
$object = $client->objects->get($uuid);

// Look up by scancode, including archived objects. Pass the raw barcode;
// the SDK URL-encodes it as a single path segment.
$object = $client->objects->getByBarcode('INV/0042');

$client->objects->patch($uuid, ['inventory_name' => 'Laptop #43']);
$client->objects->delete($uuid);

// Count
$count = $client->objects->count();

// Archive / unarchive
$client->objects->archive($uuid);
$client->objects->unarchive($uuid);

// File attachments
$client->objects->addFiles($uuid, [new FileAttachment('documents', $fileUuid)]);
$client->objects->removeFiles($uuid, [new FileAttachment('documents', $fileUuid)]);
```

### Rooms

```php
$rooms = $client->rooms->list();
$uuid = $client->rooms->create(['name' => 'Server Room', ...]);
$room = $client->rooms->get($uuid);
$client->rooms->patch($uuid, ['name' => 'Server Room A']);
$count = $client->rooms->count();
$client->rooms->delete($uuid);
```

### Locations

```php
$locations = $client->locations->list();
$uuid = $client->locations->create(['name' => 'Building A']);
$location = $client->locations->get($uuid);
$client->locations->patch($uuid, ['name' => 'Building B']);
$count = $client->locations->count();
$client->locations->delete($uuid);
```

Room and location `list()`, `get()`, and `patch()` results are flat field maps,
whether the API sends a flat record or a `{uuid, fields}` envelope. Custom
fields are preserved, and the envelope UUID is included if the inner map has
no `uuid` field. The `all()` iterators use the same normalization.

### Users

```php
use Seventhings\Models\UserListOptions;

$result = $client->users->list();
// $result->items, $result->total, $result->page, etc.

$user = $client->users->get($uuid);
$user = $client->users->getById($id);
```

### Persons

```php
use Seventhings\Models\PersonListOptions;
use Seventhings\Models\FilterObject;
use Seventhings\Models\Enums\SortDirection;

$result = $client->persons->list();
// $result->items, $result->total, $result->page, etc.

// Sorting uses the deep-object sort[field]=DIR format (a field => direction
// map), not sort_by/order. $result->sort echoes the applied sort back.
$result = $client->persons->list(new PersonListOptions(
    perPage: 25,
    sort: ['id' => SortDirection::Desc],
));

// Each PersonResponse exposes typed convenience props (uuid, email, firstname, …).
// Person fields are template-defined, so any custom field beyond those props is
// still available via the full raw map: $person->fields['custom_key'] or the
// null-safe $person->field('custom_key').
// Flat and {uuid, fields} responses are both supported. For wrapped responses,
// ->fields is the untouched inner map. ->uuid accepts person_uuid or uuid,
// preferring a non-empty legacy person_uuid when both are present.

$count = $client->persons->count();

$person = $client->persons->get($uuid);
$person = $client->persons->getById($id);

// Create — returns the new person UUID.
// Field keys are template-defined (commonly first_name / last_name).
$uuid = $client->persons->create([
    'email' => 'max@example.com',
    'first_name' => 'Max',
]);

// Update fields — the API returns no body, so re-fetch to read the result.
$client->persons->patch($uuid, ['last_name' => 'Mustermann']);
$person = $client->persons->get($uuid);

$client->persons->delete($uuid);

// Create a platform user from the person matched by a filter
$client->persons->createUser(new FilterObject(
    filter: ['email' => ['eq' => 'max@example.com']],
));
```

### Files

```php
// Upload a file
$fileUuid = $client->files->upload('report.pdf', $fileContents);

// List all files
$files = $client->files->list();

// Get metadata
$meta = $client->files->get($fileUuid);

// Download file data
$data = $client->files->getData($fileUuid);

// Download thumbnail
$thumbnail = $client->files->getThumbnail($fileUuid);
```

### Tasks

```php
use Seventhings\Models\CreateTaskRequest;
use Seventhings\Models\UpdateTaskRequest;
use Seventhings\Models\TaskListOptions;
use Seventhings\Models\TaskReferenceInput;
use Seventhings\Models\TimeInterval;
use Seventhings\Models\Enums\TaskStatus;
use Seventhings\Models\Enums\TaskReferenceType;
use Seventhings\Models\Enums\TimeIntervalUnit;

// Create (requires at least one reference and assignee)
$uuid = $client->tasks->create(new CreateTaskRequest(
    title: 'Review inventory',
    deadline: '2026-12-31',
    assignees: [$userUuid],
    references: [new TaskReferenceInput(TaskReferenceType::Asset, $objectUuid)],
    reminders: [new TimeInterval(TimeIntervalUnit::Days, 1)],
    recurringSchedule: null,
));

// Get, update, delete
$task = $client->tasks->get($uuid);
$client->tasks->update($uuid, new UpdateTaskRequest(
    title: 'Updated title',
    deadline: '2026-12-31',
    assignees: [$userUuid],
    references: [new TaskReferenceInput(TaskReferenceType::Asset, $objectUuid)],
    reminders: [new TimeInterval(TimeIntervalUnit::Days, 1)],
    recurringSchedule: null,
));
$client->tasks->delete($uuid);

// Change status
$client->tasks->updateStatus($uuid, TaskStatus::Closed);

// List with filters
$tasks = $client->tasks->list(new TaskListOptions(status: TaskStatus::Open));
```

### Rentals

```php
use Seventhings\Models\CreateRentalCaseRequest;
use Seventhings\Models\UpdateRentalCaseRequest;

$rentals = $client->rentals->list();
$uuid = $client->rentals->create(new CreateRentalCaseRequest(
    renter: null,
    references: null,
    pickupDate: null,
    returnDate: null,
    comment: 'Team event',
    recurringSchedule: null,
));
$rental = $client->rentals->get($uuid);
$client->rentals->update($uuid, new UpdateRentalCaseRequest(
    renter: null,
    references: null,
    pickupDate: null,
    returnDate: null,
    comment: 'Updated',
    recurringSchedule: null,
));
$client->rentals->delete($uuid);
```

### Resource History

```php
use Seventhings\Models\HistoryListOptions;

$options = new HistoryListOptions(page: 1, perPage: 25);
$history = $client->objects->history($objectUuid, $options);
// $history->items, ->page, ->perPage, ->total; newest changes first.

foreach ($history->items as $event) {
    echo $event['type']; // asset, task, rental_case, or object_merge
    // Dynamic maps retain properties and merge-specific payloads such as
    // user_id and absorbedObjectData.
}

// Other resources return typed history entries.
$history = $client->rooms->history($roomUuid, $options);
foreach ($history->items as $event) {
    echo $event->roomUuid, ' ', $event->occurredAt, ' ', $event->eventName;
    // Also: ->userUuid, ->description, ->details.
    // details remains a JSON-encoded snapshot string (or an empty string).
}

$client->locations->history($locationUuid, $options); // LocationHistoryEntry
$client->persons->history($personUuid, $options);    // PersonHistoryEntry
$client->tasks->history($taskUuid, $options);        // TaskHistoryEntry
$client->rentals->history($rentalUuid, $options);    // RentalCaseHistoryEntry
```

Omitting options uses the API defaults: page **1**, **50** entries per page.
The API caps history pages at **200** entries. Use `HistoryListOptions` to
request subsequent pages; resources without recorded changes return empty
items. History does not accept list filters or sorting options.

### PDF Reports

```php
use Seventhings\Models\CreateReportRequest;

$templates = $client->reports->listTemplates(); // ReportTemplate[] (uuid, name)

if ($templates !== []) {
    $pdf = $client->reports->create(new CreateReportRequest(
        reportTemplateUuid: $templates[0]->uuid,
        objectUuids: [$objectUuid1, $objectUuid2], // At least one; order is preserved.
    ));
    file_put_contents('inventory.pdf', $pdf);
}
```

`create()` returns a binary PDF string. Each call generates a new document;
the API does not store it. Failed requests throw the usual `ApiException`.

### Field Definitions

```php
use Seventhings\Models\Enums\AssetTrackingTemplate;
use Seventhings\Models\CreateFieldDefinition;
use Seventhings\Models\FieldDefinitionFieldType;
use Seventhings\Models\FieldValueConstraint;
use Seventhings\Models\FieldAttribute;
use Seventhings\Models\Enums\FieldTypeName;

// List field definitions for a template
// (AssetTrackingTemplate::Asset, ::Room, or ::Person)
$fields = $client->fieldDefinitions->list(AssetTrackingTemplate::Asset);

// Inspect a definition: is it required for this instance, and (for a
// DROPDOWN etc.) what values are allowed?
$fields[0]->isMandatory();                   // bool
$fields[0]->attribute('mandatory');          // raw attribute value or null
$fields[0]->fieldType->allowedValues();      // list<mixed> or null

// Discover instance-required fields before creating a resource. System-managed
// keys (id, uuid, timestamps, ...) are excluded automatically.
$required = $client->fieldDefinitions->mandatoryFieldDefinitions(AssetTrackingTemplate::Person);

// Fail fast: which required keys are missing from a create payload?
$missing = $client->fieldDefinitions->missingMandatoryFields(
    AssetTrackingTemplate::Person,
    ['email' => 'max@example.com'],
);
if ($missing !== []) {
    throw new \InvalidArgumentException('missing required fields: ' . implode(', ', $missing));
}

// Get a specific field definition
$field = $client->fieldDefinitions->get(AssetTrackingTemplate::Asset, $uuid);

// Create a custom field
$uuid = $client->fieldDefinitions->create(AssetTrackingTemplate::Asset, new CreateFieldDefinition(
    fieldType: new FieldDefinitionFieldType(FieldTypeName::Text, [
        new FieldValueConstraint('max_length', 255),
    ]),
    label: 'Serial Number',
    attributes: [
        new FieldAttribute('mandatory', 'no'),
        new FieldAttribute('mutable', 'yes'),
        new FieldAttribute('internal', 'no'),
    ],
    relations: [],
));
```

### CircularityHub

```php
use Seventhings\Models\FilterObject;
use Seventhings\Models\AddObjectEntry;

// Suggest category
$suggestion = $client->circularityHub->suggestCategory(new FilterObject(filter: ['name' => 'Monitor']));

// Items
$items = $client->circularityHub->listItems();
$item = $client->circularityHub->getItem($id);
$client->circularityHub->updateItem($id, ['price' => '25.00']);
$client->circularityHub->deleteItem($id);

// Orders
$orders = $client->circularityHub->listOrders();
$orderId = $client->circularityHub->createOrder([$itemId1, $itemId2]);
$order = $client->circularityHub->getOrder($orderId);

// Add objects to CircularityHub
$client->circularityHub->addObjects([
    $objectUuid => new AddObjectEntry(category: 'Electronics', price: '50.00'),
]);
```

## Error Handling

```php
use Seventhings\Models\ApiException;
use Seventhings\Models\NetworkException;

try {
    $client->objects->get($uuid);
} catch (ApiException $e) {
    echo $e->statusCode; // e.g. 404
    echo $e->status;     // e.g. "Not Found"
    echo $e->body;       // raw response body
    $e->isStatusCode(404); // true

    // Convenience predicates for common statuses:
    if ($e->isNotFound()) { /* 404 */ }
    // also: isUnauthorized() 401, isForbidden() 403, isConflict() 409,
    //       isRateLimited() 429, isServerError() 5xx
} catch (NetworkException $e) {
    echo $e->getMessage(); // connection error details
}
```

## ListOptions & Filtering

Use `ListOptions` for paginated, filtered, and sorted requests on Objects, Rooms, Locations, and Rentals.

```php
use Seventhings\Models\ListOptions;
use Seventhings\Models\FilterEntry;
use Seventhings\Models\Enums\FilterOperator;
use Seventhings\Models\Enums\SortDirection;

$options = new ListOptions(
    page: 1,
    perPage: 25,
    sort: ['updated_at' => SortDirection::Desc],
    filters: [
        // Static constructors avoid spelling out the operator enum:
        FilterEntry::like('inventory_name', 'Laptop'),
        FilterEntry::in('status', 'active', 'reserved'),
        // equivalent to: new FilterEntry('inventory_name', FilterOperator::Like, ['Laptop'])
    ],
);

$objects = $client->objects->list($options);
$count = $client->objects->count($options);

// Or iterate every matching object across all pages:
foreach ($client->objects->all($options) as $obj) { /* ... */ }
```

`all()` is available on `objects`, `rooms`, `locations` (yielding `Fields`),
and on `persons` / `users` (yielding their typed response objects).

Tasks and Users have their own option classes:

```php
// Tasks
$tasks = $client->tasks->list(new TaskListOptions(
    status: TaskStatus::Open,
    assignee: $userUuid,
));

// Users
$users = $client->users->list(new UserListOptions(
    page: 1,
    perPage: 50,
    sortBy: UserSortBy::Email,
    order: UserSortOrder::Asc,
));
```

## Scope & Limitations

- **No auto-refresh** — tokens are not automatically refreshed; call `$client->auth->refresh()` and `$client->setToken()` manually.
- **No retry logic** — failed requests are not retried automatically.
- **No caching** — all calls hit the API directly.
- **CircularityHub uses integer IDs**, not UUIDs.
- **History pagination is manual** — use `HistoryListOptions`; list iterators are described above.

## Running Tests

```bash
# Unit tests (default)
composer test

# Integration tests (requires API credentials)
export SEVENTHINGS_BASE_URL=https://your-instance.7things.de
export SEVENTHINGS_USERNAME=your-user
export SEVENTHINGS_PASSWORD=your-password
export SEVENTHINGS_CLIENT_ID=your-client-id
composer test:integration
```

## License

MIT
