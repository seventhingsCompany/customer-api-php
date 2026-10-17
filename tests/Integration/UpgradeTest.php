<?php

declare(strict_types=1);

namespace Seventhings\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use Seventhings\Client;
use Seventhings\Models\ApiException;
use Seventhings\Models\CreateRentalCaseRequest;
use Seventhings\Models\CreateTaskRequest;
use Seventhings\Models\Enums\RentalCaseReferenceType;
use Seventhings\Models\Enums\RenterType;
use Seventhings\Models\Enums\TaskReferenceType;
use Seventhings\Models\Enums\TimeIntervalUnit;
use Seventhings\Models\FilterEntry;
use Seventhings\Models\ListOptions;
use Seventhings\Models\RentalCaseReferenceInput;
use Seventhings\Models\RentalCaseRenter;
use Seventhings\Models\TaskReferenceInput;
use Seventhings\Models\TimeInterval;
use Seventhings\Models\UpdateRentalCaseRequest;
use Seventhings\Models\UpdateTaskRequest;
use Seventhings\Models\UserListOptions;

/** Exercises API upgrades: catalog items, timed rentals, thumbnails, reminders, rate limiting. */
final class UpgradeTest extends IntegrationTestCase
{
    /** @var list<callable(): void> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $remove) {
            try {
                $remove();
            } catch (\Throwable $e) {
                fwrite(STDERR, 'cleanup failed: ' . $e->getMessage() . "\n");
            }
        }
        $this->cleanup = [];
    }

    private function createObject(string $prefix): string
    {
        $uuid = self::$client->objects->create([
            'inventory_name' => $prefix . $this->uniqueSuffix(),
            'barcode' => strtoupper($prefix) . $this->uniqueSuffix(),
        ]);
        $this->cleanup[] = fn() => self::$client->objects->delete($uuid);
        return $uuid;
    }

    private function userUuid(): string
    {
        $users = self::$client->users->list(new UserListOptions(perPage: 1));
        $this->assertNotEmpty($users->items, 'no user available');
        return $users->items[0]->uuid;
    }

    #[Test]
    public function catalogItemsCrud(): void
    {
        $name = 'sdk-catalog-' . $this->uniqueSuffix();
        try {
            $uuid = self::$client->catalogItems->create(['catalog_object_inventory_name' => $name]);
        } catch (ApiException $e) {
            if ($e->isForbidden()) {
                $this->markTestSkipped('product catalog feature or create permission unavailable');
            }
            throw $e;
        }
        $deleted = false;
        $this->cleanup[] = function () use (&$deleted, $uuid) {
            if (!$deleted) {
                self::$client->catalogItems->delete($uuid);
            }
        };

        $item = self::$client->catalogItems->get($uuid);
        $this->assertSame($uuid, $item->uuid);
        $this->assertSame($name, $item->fields->string('catalog_object_inventory_name'));

        // PUT replaces the entire state: send the read field map back.
        $fields = ['catalog_object_inventory_name' => $name . '-updated'] + $item->fields->data;
        self::$client->catalogItems->update($uuid, $fields);
        $item = self::$client->catalogItems->get($uuid);
        $this->assertSame($name . '-updated', $item->fields->string('catalog_object_inventory_name'));

        $page = self::$client->catalogItems->list(new ListOptions(page: 1, perPage: 10, filters: [FilterEntry::eq('catalog_object_inventory_name', $name . '-updated')]));
        $this->assertCount(1, $page->items);
        $this->assertSame($uuid, $page->items[0]->uuid);

        self::$client->catalogItems->delete($uuid);
        $deleted = true;
        try {
            self::$client->catalogItems->get($uuid);
            $this->fail('expected 404 after delete');
        } catch (ApiException $e) {
            $this->assertTrue($e->isNotFound());
        }
    }

    #[Test]
    public function timedRentalCrud(): void
    {
        $objectUuid = $this->createObject('sdk-timed-rental-');
        $userUuid = $this->userUuid();
        $refs = [new RentalCaseReferenceInput(RentalCaseReferenceType::Asset, $objectUuid)];
        $renter = new RentalCaseRenter(RenterType::Plain, 'SDK integration test');
        $issue = '2099-01-01T10:00:00+02:00';
        $due = '2099-01-01T12:00:00+02:00';

        try {
            $uuid = self::$client->rentals->create(new CreateRentalCaseRequest(
                title: 'sdk-timed-rental-' . $this->uniqueSuffix(),
                issueDate: '2099-01-01',
                dueDate: '2099-01-01',
                comment: '',
                responsibleUserUuid: $userUuid,
                renter: $renter,
                references: $refs,
                attachments: [],
                isFullDay: false,
                issueAt: $issue,
                dueAt: $due,
            ));
        } catch (ApiException $e) {
            if ($e->isFeatureInactive()) {
                $this->markTestSkipped('Rentals module is not active on this instance');
            }
            throw $e;
        }
        $this->cleanup[] = fn() => self::$client->rentals->delete($uuid);

        $verify = function (?string $wantIssue, ?string $wantDue) use ($uuid): void {
            $rental = self::$client->rentals->get($uuid);
            $this->assertSame($wantIssue === null, $rental->isFullDay);
            foreach ([[$rental->issueAt, $wantIssue], [$rental->dueAt, $wantDue]] as [$got, $want]) {
                if ($want === null) {
                    $this->assertNull($got);
                    continue;
                }
                $this->assertNotNull($got);
                $this->assertEquals(new \DateTimeImmutable($want), new \DateTimeImmutable($got));
            }
        };
        $verify($issue, $due);

        $newDue = '2099-01-01T13:00:00+02:00';
        $update = fn(bool $fullDay, ?string $issueAt, ?string $dueAt, string $dueDate) => new UpdateRentalCaseRequest(
            title: 'updated timed rental',
            issueDate: '2099-01-01',
            dueDate: $dueDate,
            comment: '',
            responsibleUserUuid: $userUuid,
            renter: $renter,
            references: $refs,
            attachments: [],
            isFullDay: $fullDay,
            issueAt: $issueAt,
            dueAt: $dueAt,
        );
        self::$client->rentals->update($uuid, $update(false, $issue, $newDue, '2099-01-01'));
        $verify($issue, $newDue);

        self::$client->rentals->update($uuid, $update(true, null, null, '2099-01-02'));
        $verify(null, null);
    }

    #[Test]
    public function thumbnailCustomSize(): void
    {
        if (!function_exists('gzcompress')) {
            $this->markTestSkipped('zlib extension required to build the PNG fixture');
        }
        // The API has no file-delete endpoint; the fixture remains on the instance.
        $uuid = self::$client->files->upload('sdk-thumbnail-' . $this->uniqueSuffix() . '.png', self::solidPng(640, 480));

        $data = self::$client->files->getThumbnail($uuid, 320, 240);
        $size = getimagesizefromstring($data);
        $this->assertNotFalse($size);
        $this->assertSame([320, 240], [$size[0], $size[1]]);
        $this->assertSame($data, self::$client->files->getThumbnail($uuid, 320, 240), 'cached thumbnail differs');
    }

    #[Test]
    public function taskOptionalReminders(): void
    {
        $objectUuid = $this->createObject('sdk-reminders-');
        $assignees = [$this->userUuid()];
        $refs = [new TaskReferenceInput(TaskReferenceType::Asset, $objectUuid)];

        $uuid = self::$client->tasks->create(new CreateTaskRequest('sdk-optional-reminders', '2099-12-31', $assignees, $refs, null, null, notify: false));
        $this->cleanup[] = fn() => self::$client->tasks->delete($uuid);

        foreach ([[new TimeInterval(TimeIntervalUnit::Days, 0)], [], null] as $reminders) {
            self::$client->tasks->update($uuid, new UpdateTaskRequest('sdk-optional-reminders', '2099-12-31', $assignees, $refs, $reminders, null, notify: false));
            $task = self::$client->tasks->get($uuid);
            if ($reminders !== null) {
                $this->assertCount(count($reminders), $task->reminders);
            }
            if (count($reminders ?? []) === 1) {
                $this->assertEquals($reminders[0], $task->reminders[0]);
            }
        }
    }

    #[Test]
    public function rateLimitSpacesLiveRequests(): void
    {
        $client = Client::withToken(getenv('SEVENTHINGS_BASE_URL'), self::$client->getHttpClient()->getToken(), 400);
        $start = hrtime(true);
        for ($i = 0; $i < 4; $i++) {
            $client->auth->ping();
        }
        // Four requests at 400/min need at least three 150 ms intervals.
        $this->assertGreaterThanOrEqual(0.45, (hrtime(true) - $start) / 1e9);
    }

    #[Test]
    public function apiErrorHeadersAreRetained(): void
    {
        try {
            self::$client->objects->get('00000000-0000-0000-0000-000000000000');
            $this->fail('expected 404');
        } catch (ApiException $e) {
            $this->assertTrue($e->isNotFound());
            $this->assertNotEmpty($e->headers);
        }
    }

    private static function solidPng(int $width, int $height): string
    {
        $chunk = static fn(string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $row = "\0" . str_repeat("\x50\x8c\xc8", $width);
        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress(str_repeat($row, $height)))
            . $chunk('IEND', '');
    }
}
