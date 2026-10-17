<?php

declare(strict_types=1);

namespace Seventhings\Models;

class ApiException extends \RuntimeException
{
    /** HTTP date formats accepted for Retry-After (IMF-fixdate, RFC 850, asctime). */
    private const HTTP_DATE_FORMATS = [
        '!D, d M Y H:i:s \G\M\T',
        '!l, d-M-y H:i:s \G\M\T',
        '!D M j H:i:s Y',
    ];

    /**
     * @param array<string, string[]> $headers Response headers, including
     *     Retry-After when sent. Headers are not included in the message.
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $status,
        public readonly string $body,
        ?\Throwable $previous = null,
        public readonly array $headers = [],
    ) {
        parent::__construct(
            sprintf('seventhings API error %d (%s): %s', $statusCode, $status, $body),
            $statusCode,
            $previous,
        );
    }

    /**
     * Returns all values of a response header (case-insensitive name).
     *
     * @return string[]
     */
    public function header(string $name): array
    {
        $values = [];
        foreach ($this->headers as $key => $headerValues) {
            if (strcasecmp((string) $key, $name) === 0) {
                array_push($values, ...$headerValues);
            }
        }
        return $values;
    }

    /**
     * Parses the Retry-After header as delay-seconds or an HTTP date relative
     * to $now (default: the current time) and returns the delay in whole
     * seconds; HTTP dates are rounded up so callers never retry early. Past
     * dates return 0. Missing, repeated, invalid or overflowing values return
     * null. This helper does not wait or retry; callers decide whether to
     * requeue a request.
     */
    public function retryAfter(?\DateTimeInterface $now = null): ?int
    {
        $values = $this->header('Retry-After');
        if (count($values) !== 1) {
            return null;
        }
        $value = trim($values[0]);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $digits = ltrim($value, '0');
            if ($digits === '') {
                return 0;
            }
            $max = (string) PHP_INT_MAX;
            if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
                return null;
            }
            return (int) $digits;
        }

        $date = self::parseHttpDate($value);
        if ($date === null) {
            return null;
        }
        $now ??= new \DateTimeImmutable();
        $delay = (float) $date->format('U.u') - (float) $now->format('U.u');

        return $delay <= 0 ? 0 : (int) ceil($delay);
    }

    private static function parseHttpDate(string $value): ?\DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');
        foreach (self::HTTP_DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value, $utc);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }
        return null;
    }

    public function isStatusCode(int $code): bool
    {
        return $this->statusCode === $code;
    }

    /** Reports whether the error is a 404 Not Found. */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    /** Reports whether the error is a 401 Unauthorized. */
    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    /** Reports whether the error is a 403 Forbidden. */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    /** Reports the API's explicit inactive-feature response, not a permission denial. */
    public function isFeatureInactive(): bool
    {
        if (!$this->isForbidden()) {
            return false;
        }

        try {
            $payload = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return is_array($payload)
            && ($payload['message'] ?? null) === 'The required feature for this endpoint is not active';
    }

    /** Reports whether the error is a 409 Conflict. */
    public function isConflict(): bool
    {
        return $this->statusCode === 409;
    }

    /** Reports whether the error is a 429 Too Many Requests. */
    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    /** Reports whether the error is a 5xx server error. */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}
