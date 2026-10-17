<?php

declare(strict_types=1);

namespace Seventhings\Tests\Unit\Models;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seventhings\Models\PersonResponse;

final class PersonResponseTest extends TestCase
{
    public static function uuidFormats(): iterable
    {
        yield 'person_uuid' => [['person_uuid' => 'legacy'], 'legacy'];
        yield 'unmapped uuid' => [['uuid' => 'current'], ''];
        yield 'both' => [['person_uuid' => 'legacy', 'uuid' => 'current'], 'legacy'];
        yield 'empty person_uuid' => [['person_uuid' => '', 'uuid' => 'current'], ''];
        yield 'null person_uuid' => [['person_uuid' => null, 'uuid' => 'current'], ''];
        yield 'missing' => [[], ''];
    }

    #[Test]
    #[DataProvider('uuidFormats')]
    public function readsFlatIdentityAndPreservesRawFields(array $data, string $expected): void
    {
        $data['custom'] = ['nested' => true];
        $person = PersonResponse::fromArray($data);
        $this->assertSame($expected, $person->uuid);
        $this->assertSame($data, $person->fields);
    }

    #[Test]
    public function preservesArbitraryFieldsValues(): void
    {
        foreach ([['uuid' => 'p-1', 'fields' => 'invalid'], ['uuid' => 'p-1', 'fields' => ['id' => 'invalid']]] as $data) {
            $person = PersonResponse::fromArray($data);
            $this->assertSame('', $person->uuid);
            $this->assertSame(0, $person->id);
            $this->assertSame($data, $person->fields);
        }
    }

    public static function invalidFields(): iterable
    {
        yield 'invalid person_uuid' => [['person_uuid' => 42]];
        yield 'invalid typed field' => [['person_uuid' => 'p-1', 'id' => 'invalid']];
    }

    #[Test]
    #[DataProvider('invalidFields')]
    public function rejectsInvalidTypes(array $data): void
    {
        $this->expectException(\TypeError::class);
        PersonResponse::fromArray($data);
    }
}
