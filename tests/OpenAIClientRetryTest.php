<?php

declare(strict_types=1);

namespace HyperfAgents\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HyperfAgents\Client\OpenAIClient;
use HyperfAgents\Client\OpenAIClientInterface;
use HyperfAgents\Exception\CircuitBreakerOpenException;
use HyperfAgents\Exception\LlmApiException;
use HyperfAgents\Resilience\CircuitBreaker;
use PHPUnit\Framework\TestCase;

class OpenAIClientRetryTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $client = new OpenAIClient(apiKey: 'sk-test');
        $this->assertInstanceOf(OpenAIClientInterface::class, $client);
    }

    public function testRetrySucceedsAfter429WithDelays(): void
    {
        $successBody = (string) json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]],
        ]);

        $mock = new MockHandler([
            new Response(429),
            new Response(429),
            new Response(200, ['Content-Type' => 'application/json'], $successBody),
        ]);

        $stack = HandlerStack::create($mock);
        /** @var array<int, array<string, mixed>> $history */
        $history = [];
        $stack->push(Middleware::history($history));

        $delays = [];
        $client = new OpenAIClient(
            apiKey: 'sk-test',
            clientOptions: ['handler' => $stack],
            maxAttempts: 3,
            initialDelayMs: 200,
            multiplier: 2.0,
            sleeper: function (int $ms) use (&$delays): void {
                $delays[] = $ms;
            }
        );

        $res = $client->chatCompletions(['model' => 'gpt-4o']);

        $this->assertSame('ok', $res['choices'][0]['message']['content'] ?? null);
        $this->assertCount(3, $history);
        $this->assertSame([200, 400], $delays);
    }

    public function testClientErrorDoesNotRetry(): void
    {
        $mock = new MockHandler([new Response(400)]);
        $stack = HandlerStack::create($mock);
        /** @var array<int, array<string, mixed>> $history */
        $history = [];
        $stack->push(Middleware::history($history));

        $client = new OpenAIClient(
            apiKey: 'sk-test',
            clientOptions: ['handler' => $stack],
            maxAttempts: 3,
            sleeper: static fn (int $ms): null => null
        );

        $this->expectException(LlmApiException::class);
        try {
            $client->chatCompletions(['model' => 'gpt-4o']);
        } finally {
            $this->assertCount(1, $history);
        }
    }

    public function testPersistent500ExhaustsAndThrows(): void
    {
        $mock = new MockHandler([new Response(500), new Response(500), new Response(500)]);
        $stack = HandlerStack::create($mock);
        /** @var array<int, array<string, mixed>> $history */
        $history = [];
        $stack->push(Middleware::history($history));

        $client = new OpenAIClient(
            apiKey: 'sk-test',
            clientOptions: ['handler' => $stack],
            maxAttempts: 3,
            initialDelayMs: 0,
            sleeper: static fn (int $ms): null => null
        );

        $this->expectException(LlmApiException::class);
        try {
            $client->chatCompletions(['model' => 'gpt-4o']);
        } finally {
            $this->assertCount(3, $history);
        }
    }

    public function testCircuitBreakerIntegration(): void
    {
        $mock = new MockHandler([new Response(500)]);
        $stack = HandlerStack::create($mock);
        /** @var array<int, array<string, mixed>> $history */
        $history = [];
        $stack->push(Middleware::history($history));

        $cb = new CircuitBreaker(
            failureThreshold: 1,
            recoverySeconds: 1000.0,
            clock: static fn (): float => 1000.0
        );

        $client = new OpenAIClient(
            apiKey: 'sk-test',
            clientOptions: ['handler' => $stack],
            maxAttempts: 1,
            sleeper: static fn (int $ms): null => null,
            circuitBreaker: $cb
        );

        try {
            $client->chatCompletions(['model' => 'gpt-4o']);
            $this->fail('Expected LlmApiException on first call');
        } catch (LlmApiException) {
            // expected
        }

        $this->assertSame(CircuitBreaker::STATE_OPEN, $cb->getState());
        $requestsBefore = count($history);

        $this->expectException(CircuitBreakerOpenException::class);
        try {
            $client->chatCompletions(['model' => 'gpt-4o']);
        } finally {
            $this->assertCount($requestsBefore, $history);
        }
    }
}
