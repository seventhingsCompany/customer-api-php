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
        yield 'legacy' => [['person_uuid' => 'legacy'], 'legacy'];
        yield 'current' => [['uuid' => 'current'], 'current'];
        yield 'both' => [['person_uuid' => 'legacy', 'uuid' => 'current'], 'legacy'];
        yield 'empty legacy' => [['person_uuid' => '', 'uuid' => 'current'], 'current'];
        yield 'null legacy' => [['person_uuid' => null, 'uuid' => 'current'], 'current'];
        yield 'missing' => [[], ''];
        yield 'null fields' => [['uuid' => 'current', 'fields' => null], 'current'];
    }

    #[Test]
    #[DataProvider('uuidFormats')]
    public function acceptsUuidAliasesAndPreservesRawFields(array $data, string $expected): void
    {
        $data['custom'] = ['nested' => true];
        $person = PersonResponse::fromArray($data);
        $this->assertSame($expected, $person->uuid);
        $this->assertSame($data, $person->fields);
    }

    #[Test]
    public function wrappedIdentityUsesLegacyValueThenEnvelope(): void
    {
        foreach (['legacy', '', null] as $legacyUuid) {
            $fields = ['person_uuid' => $legacyUuid, 'custom' => true];
            $person = PersonResponse::fromArray(['uuid' => 'envelope', 'fields' => $fields]);
            $this->assertSame($legacyUuid === 'legacy' ? 'legacy' : 'envelope', $person->uuid);
            $this->assertSame($fields, $person->fields);
        }
        $person = PersonResponse::fromArray(['uuid' => 'envelope', 'fields' => []]);
        $this->assertSame('envelope', $person->uuid);
        $this->assertSame([], $person->fields);
    }

    public static function invalidFields(): iterable
    {
        yield 'invalid uuid' => [['uuid' => 42]];
        yield 'invalid legacy uuid' => [['person_uuid' => 42]];
        yield 'invalid alias alongside legacy' => [['uuid' => 42, 'person_uuid' => 'legacy']];
        yield 'invalid envelope' => [['uuid' => 'p-1', 'fields' => 'invalid']];
        yield 'invalid typed field' => [['uuid' => 'p-1', 'fields' => ['id' => 'invalid']]];
    }

    #[Test]
    #[DataProvider('invalidFields')]
    public function rejectsInvalidTypes(array $data): void
    {
        $this->expectException(\TypeError::class);
        PersonResponse::fromArray($data);
    }
}
