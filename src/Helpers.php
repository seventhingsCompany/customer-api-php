<?php

declare(strict_types=1);

namespace Seventhings;

final class Helpers
{
    /**
     * Page size used by the `all()` auto-paginating iterators when the caller
     * leaves perPage unset. Keeps iteration from making one request per row.
     */
    public const DEFAULT_PAGE_SIZE = 100;

    public static function uuidFromLocationHeader(Response $response): string
    {
        $location = $response->headerLine('Location');
        if ($location === '') {
            throw new \RuntimeException('missing Location header');
        }

        $uuid = basename($location);
        if ($uuid === '' || $uuid === '.' || $uuid === '/') {
            throw new \RuntimeException('empty path in Location header: ' . $location);
        }

        return $uuid;
    }

    public static function uuidFromFileUpload(Response $response): string
    {
        $locationUuid = $response->headerLine('Location-UUID');
        if ($locationUuid !== '') {
            return $locationUuid;
        }

        return self::uuidFromLocationHeader($response);
    }

    public static function intFromLocationIdHeader(Response $response): int
    {
        $raw = $response->headerLine('Location-Id');
        if ($raw === '') {
            throw new \RuntimeException('missing Location-Id header');
        }

        if (!ctype_digit($raw) && !(str_starts_with($raw, '-') && ctype_digit(substr($raw, 1)))) {
            throw new \RuntimeException(sprintf('invalid Location-Id header "%s"', $raw));
        }

        return (int) $raw;
    }
}
