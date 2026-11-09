<?php

declare(strict_types=1);

namespace HyperfAgents\Tests;

use HyperfAgents\Agent\Agent;
use HyperfAgents\Agent\Handoff;
use HyperfAgents\Tool\ConcurrentToolExecutor;
use HyperfAgents\Tool\ToolInterface;
use HyperfAgents\Tool\ToolResult;
use PHPUnit\Framework\TestCase;

class ToolTest extends TestCase
{
    public function testToolResultNormal(): void
    {
        $result = new ToolResult(
            toolCallId: 'call_123',
            toolName: 'calculate',
            output: ['sum' => 42],
            handoff: null,
            success: true,
            executionTimeMs: 12.5
        );

        $this->assertFalse($result->isHandoff());
        $this->assertNull($result->getHandoff());
        $this->assertSame('{"sum":42}', $result->toMessageContent());
    }

    public function testToolResultWithHandoff(): void
    {
        $targetAgent = new Agent('EscalationAgent', 'Handle complex issues');
        $handoff = new Handoff($targetAgent);

        $result = new ToolResult(
            toolCallId: 'call_handoff',
            toolName: 'transfer_agent',
            output: $handoff
        );

        $this->assertTrue($result->isHandoff());
        $this->assertSame($handoff, $result->getHandoff());
        $this->assertSame('Transferred to agent: EscalationAgent', $result->toMessageContent());
    }

    public function testConcurrentToolExecutorSingleCallable(): void
    {
        $executor = new ConcurrentToolExecutor();
        $tools = [
            'add' => [
                'handler' => fn (array $args) => ($args['a'] ?? 0) + ($args['b'] ?? 0),
            ],
        ];

        $toolCalls = [
            [
                'id' => 'call_1',
                'function' => [
                    'name' => 'add',
                    'arguments' => json_encode(['a' => 10, 'b' => 20]),
                ],
            ],
        ];

        $results = $executor->executeAll($toolCalls, $tools);

        $this->assertCount(1, $results);
        $this->assertSame('call_1', $results[0]->toolCallId);
        $this->assertSame('add', $results[0]->toolName);
        $this->assertSame(30, $results[0]->output);
        $this->assertTrue($results[0]->success);
    }

    public function testConcurrentToolExecutorUnregisteredTool(): void
    {
        $executor = new ConcurrentToolExecutor();
        $toolCalls = [
            [
                'id' => 'call_unknown',
                'function' => [
                    'name' => 'non_existent_tool',
                    'arguments' => '{}',
                ],
            ],
        ];

        $results = $executor->executeAll($toolCalls, []);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->success);
        $this->assertStringContainsString('not found', $results[0]->output);
    }

    public function testConcurrentToolExecutorExceptionHandling(): void
    {
        $executor = new ConcurrentToolExecutor();
        $tools = [
            'broken_tool' => [
                'handler' => function () {
                    throw new \RuntimeException('Database connection lost');
                },
            ],
        ];

        $toolCalls = [
            [
                'id' => 'call_fail',
                'function' => [
                    'name' => 'broken_tool',
                    'arguments' => '{}',
                ],
            ],
        ];

        $results = $executor->executeAll($toolCalls, $tools);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->success);
        $this->assertStringContainsString('Database connection lost', $results[0]->errorMessage);
    }

    public function testConcurrentToolExecutorWithToolInterface(): void
    {
        $executor = new ConcurrentToolExecutor();

        $tool = new class implements ToolInterface {
            public function getName(): string
            {
                return 'ping';
            }

            public function getDescription(): string
            {
                return 'ping service';
            }

            public function getParameters(): array
            {
                return [];
            }

            public function execute(array $arguments, array $contextVariables = []): mixed
            {
                return 'pong: ' . ($contextVariables['user_id'] ?? 'guest');
            }
        };

        $tools = [
            'ping' => [
                'handler' => $tool,
            ],
        ];

        $toolCalls = [
            [
                'id' => 'call_ping',
                'function' => [
                    'name' => 'ping',
                    'arguments' => '{}',
                ],
            ],
        ];

        $results = $executor->executeAll($toolCalls, $tools, ['user_id' => 'admin_99']);
        $this->assertCount(1, $results);
        $this->assertSame('pong: admin_99', $results[0]->output);
    }
}
