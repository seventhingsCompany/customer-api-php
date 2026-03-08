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

// List objects
$objects = $client->objects->list();

// Create (field names are instance-specific)
$uuid = $client->objects->create([
    'inventory_name' => 'Laptop #42',
    'barcode' => 'INV-0042',
]);

// Get, patch, delete
$object = $client->objects->get($uuid);
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

### Users

```php
use Seventhings\Models\UserListOptions;

$result = $client->users->list();
// $result->items, $result->total, $result->page, etc.

$user = $client->users->get($uuid);
$user = $client->users->getById($id);
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

### Field Definitions

```php
use Seventhings\Models\Enums\AssetTrackingTemplate;
use Seventhings\Models\CreateFieldDefinition;
use Seventhings\Models\FieldDefinitionFieldType;
use Seventhings\Models\FieldValueConstraint;
use Seventhings\Models\FieldAttribute;
use Seventhings\Models\Enums\FieldTypeName;

// List field definitions for a template
$fields = $client->fieldDefinitions->list(AssetTrackingTemplate::Asset);

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
        new FilterEntry('inventory_name', FilterOperator::Like, ['Laptop']),
    ],
);

$objects = $client->objects->list($options);
$count = $client->objects->count($options);
```

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
- **No pagination iterators** — pagination is manual via `ListOptions`.

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
