<?php

declare(strict_types=1);

namespace Seventhings\Models;

/**
 * Fields wraps a dynamic resource body (object/asset, room, location,
 * circularity-hub item, or a PersonResponse's raw field map) with type-safe
 * accessors. These resources are returned as associative arrays because their
 * schema is instance-defined; wrap one with `new Fields($array)` to read
 * values without brittle manual casts.
 *
 * Every typed getter returns null when the key is absent, null, or holds a
 * value of a different type, so a caller can use `?? $default` uniformly.
 */
readonly class Fields
{
    /** Date/datetime formats tried by {@see time()}, in order. */
    private const TIME_FORMATS = [
        'Y-m-d',
        'Y-m-d H:i:s',
        \DateTimeInterface::RFC3339,
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public array $data = []) {}

    /** Reports whether $key is present with a non-null value. */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /** Returns the raw value for $key, or null if absent. */
    public function raw(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /** Returns the value for $key if it is a string, else null. */
    public function string(string $key): ?string
    {
        $v = $this->data[$key] ?? null;
        return is_string($v) ? $v : null;
    }

    /**
     * Returns the value for $key as an int. Accepts int and integral float
     * (JSON numbers decode to int/float). A non-integral float returns null.
     */
    public function int(string $key): ?int
    {
        $v = $this->data[$key] ?? null;
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v) && $v === (float) (int) $v) {
            return (int) $v;
        }
        return null;
    }

    /** Returns the value for $key as a float (accepts int), else null. */
    public function float(string $key): ?float
    {
        $v = $this->data[$key] ?? null;
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        return null;
    }

    /** Returns the value for $key if it is a bool, else null. */
    public function bool(string $key): ?bool
    {
        $v = $this->data[$key] ?? null;
        return is_bool($v) ? $v : null;
    }

    /**
     * Returns the value for $key parsed as an immutable date-time. Accepts
     * date-only, datetime, and RFC3339 layouts. Non-string or unparseable
     * values return null.
     */
    public function time(string $key): ?\DateTimeImmutable
    {
        $v = $this->data[$key] ?? null;
        if (!is_string($v) || $v === '') {
            return null;
        }
        foreach (self::TIME_FORMATS as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $v);
            // createFromFormat is lenient (it can succeed on trailing junk with
            // only a warning), so require a clean parse with no warnings/errors.
            $errors = \DateTimeImmutable::getLastErrors();
            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed;
            }
        }
        return null;
    }

    /** Returns the "uuid" field as a string, or null if absent or not a string. */
    public function uuid(): ?string
    {
        return $this->string('uuid');
    }

    /** Returns the "name" field as a string, or null if absent or not a string. */
    public function name(): ?string
    {
        return $this->string('name');
    }
}
