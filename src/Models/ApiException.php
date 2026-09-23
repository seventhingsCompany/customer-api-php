<?php

declare(strict_types=1);

namespace Seventhings\Models;

class ApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $status,
        public readonly string $body,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('seventhings API error %d (%s): %s', $statusCode, $status, $body),
            $statusCode,
            $previous,
        );
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
