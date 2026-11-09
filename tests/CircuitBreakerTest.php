<?php

declare(strict_types=1);

namespace HyperfAgents\Tests;

use HyperfAgents\Exception\CircuitBreakerOpenException;
use HyperfAgents\Resilience\CircuitBreaker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CircuitBreakerTest extends TestCase
{
    public function testStartsClosedWithZeroFailures(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 3, recoverySeconds: 10.0);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $cb->getState());
        $this->assertSame(0, $cb->getFailureCount());
    }

    public function testOpensAfterFailureThreshold(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 2, recoverySeconds: 30.0);

        for ($i = 0; $i < 2; ++$i) {
            try {
                $cb->call(static fn () => throw new RuntimeException('fail'));
            } catch (RuntimeException $e) {
                $this->assertSame('fail', $e->getMessage());
            }
        }

        $this->assertSame(CircuitBreaker::STATE_OPEN, $cb->getState());
        $this->assertSame(2, $cb->getFailureCount());
    }

    public function testShortCircuitsWhileOpen(): void
    {
        $now = 1000.0;
        $cb = new CircuitBreaker(
            failureThreshold: 1,
            recoverySeconds: 30.0,
            clock: static function () use (&$now): float {
                return $now;
            }
        );

        try {
            $cb->call(static fn () => throw new RuntimeException('err'));
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(CircuitBreaker::STATE_OPEN, $cb->getState());

        $this->expectException(CircuitBreakerOpenException::class);
        $cb->call(static fn () => 'should not execute');
    }

    public function testHalfOpenTrialSucceedsAndCloses(): void
    {
        $now = 1000.0;
        $cb = new CircuitBreaker(
            failureThreshold: 1,
            recoverySeconds: 30.0,
            clock: static function () use (&$now): float {
                return $now;
            }
        );

        try {
            $cb->call(static fn () => throw new RuntimeException('err'));
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(CircuitBreaker::STATE_OPEN, $cb->getState());

        // Advance time past recovery window
        $now += 31.0;
        $this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $cb->getState());

        $result = $cb->call(static fn () => 'recovered');
        $this->assertSame('recovered', $result);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $cb->getState());
        $this->assertSame(0, $cb->getFailureCount());
    }

    public function testFailedTrialReopens(): void
    {
        $now = 1000.0;
        $cb = new CircuitBreaker(
            failureThreshold: 1,
            recoverySeconds: 30.0,
            clock: static function () use (&$now): float {
                return $now;
            }
        );

        try {
            $cb->call(static fn () => throw new RuntimeException('first err'));
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(CircuitBreaker::STATE_OPEN, $cb->getState());

        // Advance past recovery window
        $now += 31.0;
        $this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $cb->getState());

        $caught = false;
        try {
            $cb->call(static fn () => throw new RuntimeException('trial failed'));
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertSame('trial failed', $e->getMessage());
        }
        $this->assertTrue($caught);

        // Breaker should be open again
        $this->assertSame(CircuitBreaker::STATE_OPEN, $cb->getState());

        // Immediately calling again throws CircuitBreakerOpenException
        $this->expectException(CircuitBreakerOpenException::class);
        $cb->call(static fn () => 'blocked');
    }

    public function testSuccessfulCallResetsFailureCount(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 3, recoverySeconds: 10.0);

        try {
            $cb->call(static fn () => throw new RuntimeException('transient'));
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(1, $cb->getFailureCount());

        $val = $cb->call(static fn () => 42);
        $this->assertSame(42, $val);
        $this->assertSame(0, $cb->getFailureCount());
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $cb->getState());
    }
}
