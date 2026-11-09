<?php

declare(strict_types=1);

namespace HyperfAgents\Resilience;

use HyperfAgents\Exception\CircuitBreakerOpenException;
use Throwable;

final class CircuitBreaker
{
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    private string $state = self::STATE_CLOSED;

    private int $failureCount = 0;

    private ?float $openedAt = null;

    /**
     * @var callable(): float
     */
    private $clock;

    public function __construct(
        private int $failureThreshold = 5,
        private float $recoverySeconds = 30.0,
        ?callable $clock = null
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws CircuitBreakerOpenException
     * @throws Throwable
     */
    public function call(callable $operation): mixed
    {
        $currentState = $this->getState();

        if ($currentState === self::STATE_OPEN) {
            throw new CircuitBreakerOpenException('Circuit breaker is open; upstream calls are temporarily suspended.');
        }

        if ($currentState === self::STATE_HALF_OPEN) {
            $this->state = self::STATE_HALF_OPEN;

            try {
                $result = $operation();
                $this->state = self::STATE_CLOSED;
                $this->failureCount = 0;
                $this->openedAt = null;

                return $result;
            } catch (Throwable $e) {
                $this->state = self::STATE_OPEN;
                $this->openedAt = $this->now();

                throw $e;
            }
        }

        // STATE_CLOSED
        try {
            $result = $operation();
            $this->failureCount = 0;

            return $result;
        } catch (Throwable $e) {
            ++$this->failureCount;
            if ($this->failureCount >= $this->failureThreshold) {
                $this->state = self::STATE_OPEN;
                $this->openedAt = $this->now();
            }

            throw $e;
        }
    }

    public function getState(): string
    {
        if ($this->state === self::STATE_OPEN && $this->openedAt !== null) {
            if (($this->now() - $this->openedAt) >= $this->recoverySeconds) {
                return self::STATE_HALF_OPEN;
            }
        }

        return $this->state;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    private function now(): float
    {
        return (float) ($this->clock)();
    }
}
