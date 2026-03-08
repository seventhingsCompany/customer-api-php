<?php

declare(strict_types=1);

namespace Seventhings;

readonly class Response
{
    /**
     * @param array<string, string[]> $headers
     */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
    ) {}

    public function headerLine(string $name): string
    {
        $lower = strtolower($name);
        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $lower) {
                return $values[0] ?? '';
            }
        }
        return '';
    }

    public function json(): array
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }
}
