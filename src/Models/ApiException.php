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
