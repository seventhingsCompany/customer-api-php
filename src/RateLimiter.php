<?php

declare(strict_types=1);

namespace Seventhings;

/**
 * Spaces requests evenly at a fixed rate, without bursts. The first request
 * is immediate; each later request waits until one interval has passed since
 * the previous slot. Idle time does not accumulate into a burst.
 *
 * @internal
 */
final class RateLimiter
{
    public const DEFAULT_REQUESTS_PER_MINUTE = 200;

    private float $next = 0.0;

    /** @var \Closure(): float */
    private \Closure $clock;

    /** @var \Closure(float): void */
    private \Closure $sleep;

    /**
     * @param (callable(): float)|null $clock Monotonic time in seconds.
     * @param (callable(float): void)|null $sleep Sleeps for the given seconds.
     */
    private function __construct(
        private readonly float $interval,
        ?callable $clock = null,
        ?callable $sleep = null,
    ) {
        $this->clock = $clock !== null ? $clock(...) : static fn(): float => hrtime(true) / 1e9;
        $this->sleep = $sleep !== null ? $sleep(...) : static function (float $seconds): void {
            usleep((int) ceil($seconds * 1e6));
        };
    }

    /**
     * Returns a limiter for the given rate, or null to disable throttling
     * when $requestsPerMinute is zero or less.
     *
     * @param (callable(): float)|null $clock
     * @param (callable(float): void)|null $sleep
     */
    public static function perMinute(int $requestsPerMinute, ?callable $clock = null, ?callable $sleep = null): ?self
    {
        if ($requestsPerMinute <= 0) {
            return null;
        }

        return new self(60.0 / $requestsPerMinute, $clock, $sleep);
    }

    /** Blocks until the next request slot is available and reserves it. */
    public function wait(): void
    {
        $now = ($this->clock)();
        $delay = $this->next - $now;
        if ($delay > 0) {
            ($this->sleep)($delay);
            $now = max(($this->clock)(), $this->next);
        }
        $this->next = $now + $this->interval;
    }
}
