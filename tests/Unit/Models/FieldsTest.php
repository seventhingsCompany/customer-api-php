<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Models;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\Models\Fields;

final class FieldsTest extends TestCase
{
    #[Test]
    public function typedAccessorsReturnValueOrNull(): void
    {
        $fields = new Fields([
            'name' => 'Rack A',
            'count' => 5,
            'price' => 12.5,
            'active' => true,
            'ratio' => 3.5,
        ]);

        $this->assertSame('Rack A', $fields->string('name'));
        $this->assertSame(5, $fields->int('count'));
        $this->assertSame(12.5, $fields->float('price'));
        $this->assertTrue($fields->bool('active'));

        // Wrong type → null, not a coerced value.
        $this->assertNull($fields->int('name'));
        $this->assertNull($fields->string('count'));
        $this->assertNull($fields->bool('count'));
        // Non-integral float is not an int.
        $this->assertNull($fields->int('ratio'));
        // int is accepted as float.
        $this->assertSame(5.0, $fields->float('count'));
        // Absent key → null.
        $this->assertNull($fields->string('missing'));
    }

    #[Test]
    public function hasAndRawReflectPresence(): void
    {
        $fields = new Fields(['a' => 'x', 'b' => null]);

        $this->assertTrue($fields->has('a'));
        $this->assertFalse($fields->has('b'));   // present but null → not "has"
        $this->assertFalse($fields->has('c'));

        $this->assertSame('x', $fields->raw('a'));
        $this->assertNull($fields->raw('c'));
    }

    #[Test]
    public function timeParsesDateAndDatetime(): void
    {
        $fields = new Fields([
            'day' => '2026-07-04',
            'moment' => '2026-07-04 13:45:00',
            'bad' => 'not-a-date',
        ]);

        $this->assertSame('2026-07-04', $fields->time('day')?->format('Y-m-d'));
        $this->assertSame('2026-07-04 13:45:00', $fields->time('moment')?->format('Y-m-d H:i:s'));
        $this->assertNull($fields->time('bad'));
        $this->assertNull($fields->time('missing'));
    }

    #[Test]
    public function uuidAndNameConvenience(): void
    {
        $fields = new Fields(['uuid' => 'u-1', 'name' => 'Room 1']);

        $this->assertSame('u-1', $fields->uuid());
        $this->assertSame('Room 1', $fields->name());
        $this->assertNull((new Fields([]))->uuid());
    }
}
