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
}
