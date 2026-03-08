<?php

declare(strict_types=1);

namespace Seventhings\Models;

class NetworkException extends \RuntimeException
{
    public function __construct(string $message, \Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
