<?php

declare(strict_types=1);

namespace HyperfAgents\Tests;

use HyperfAgents\Agent\Agent;
use HyperfAgents\Agent\Handoff;
use HyperfAgents\Client\OpenAIClient;
use HyperfAgents\Orchestrator\RunResult;
use HyperfAgents\Orchestrator\Swarm;
use HyperfAgents\Orchestrator\SwarmInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SwarmTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testSwarmImplementsInterface(): void
    {
        $swarm = new Swarm();
        $this->assertInstanceOf(SwarmInterface::class, $swarm);
    }

    public function testSwarmDirectResponseWithoutTools(): void
    {
        /** @var OpenAIClient&Mockery\MockInterface $mockClient */
        $mockClient = Mockery::mock(OpenAIClient::class);
        $mockClient->shouldReceive('chatCompletions')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello! How can I help you today?',
                        ],
                    ],
                ],
            ]);

        $swarm = new Swarm(client: $mockClient);
        $agent = new Agent('Greeter', 'You greet users politely');

        $result = $swarm->run(
            agent: $agent,
            messages: [
                ['role' => 'user', 'content' => 'Hi'],
            ]
        );

        $this->assertInstanceOf(RunResult::class, $result);
        $this->assertSame('Hello! How can I help you today?', $result->getContent());
        $this->assertSame(1, $result->turns);
        $this->assertSame('Greeter', $result->lastAgent->getName());
        $this->assertCount(2, $result->messages); // user message + assistant message
    }

    public function testSwarmWithToolCallAndExecution(): void
    {
        /** @var OpenAIClient&Mockery\MockInterface $mockClient */
        $mockClient = Mockery::mock(OpenAIClient::class);

        // Turn 1: model returns tool call
        $mockClient->shouldReceive('chatCompletions')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_calc_1',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'multiply',
                                        'arguments' => (string) json_encode(['a' => 6, 'b' => 7]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Turn 2: model sees tool output and returns final text
        $mockClient->shouldReceive('chatCompletions')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'The product of 6 and 7 is 42.',
                        ],
                    ],
                ],
            ]);

        $agent = new Agent('MathAgent', 'You are a math tutor');
        $agent->addTool('multiply', fn (array $args) => $args['a'] * $args['b']);

        $swarm = new Swarm(client: $mockClient);
        $result = $swarm->run(
            agent: $agent,
            messages: [
                ['role' => 'user', 'content' => 'What is 6 * 7?'],
            ]
        );

        $this->assertSame(2, $result->turns);
        $this->assertSame('The product of 6 and 7 is 42.', $result->getContent());

        // Messages history:
        // 0: user
        // 1: assistant (tool call)
        // 2: tool (tool result)
        // 3: assistant (final text)
        $this->assertCount(4, $result->messages);
        $this->assertSame('tool', $result->messages[2]['role']);
        $this->assertSame('42', $result->messages[2]['content']);
    }

    public function testSwarmWithHandoff(): void
    {
        /** @var OpenAIClient&Mockery\MockInterface $mockClient */
        $mockClient = Mockery::mock(OpenAIClient::class);

        $specialistAgent = new Agent('RefundSpecialist', 'Handle refunds');

        // Turn 1: TriageAgent calls transfer_to_refund
        $mockClient->shouldReceive('chatCompletions')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_transfer_1',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'transfer_to_refund',
                                        'arguments' => '{}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Turn 2: RefundSpecialist answers
        $mockClient->shouldReceive('chatCompletions')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Refund processed successfully.',
                        ],
                    ],
                ],
            ]);

        $triageAgent = new Agent('TriageAgent', 'Route inquiries');
        $triageAgent->addTool('transfer_to_refund', fn () => new Handoff(
            targetAgent: $specialistAgent,
            contextVariables: ['refund_authorized' => true]
        ));

        $swarm = new Swarm(client: $mockClient);
        $result = $swarm->run(
            agent: $triageAgent,
            messages: [
                ['role' => 'user', 'content' => 'I want a refund'],
            ]
        );

        $this->assertSame(2, $result->turns);
        $this->assertSame('RefundSpecialist', $result->lastAgent->getName());
        $this->assertSame('Refund processed successfully.', $result->getContent());
        $this->assertTrue($result->contextVariables['refund_authorized'] ?? false);
        $this->assertTrue($result->isCompleted());
        $this->assertFalse($result->isMaxTurnsReached());
    }

    public function testMaxTurnsReachedStatus(): void
    {
        /** @var OpenAIClient&Mockery\MockInterface $mockClient */
        $mockClient = Mockery::mock(OpenAIClient::class);

        // Infinite loop: model keeps asking for tool call
        $mockClient->shouldReceive('chatCompletions')
            ->times(3)
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_loop',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'loop_tool',
                                        'arguments' => '{}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $agent = new Agent('Looper', 'loop instructions');
        $agent->addTool('loop_tool', fn () => 'looping...');

        $swarm = new Swarm(client: $mockClient);
        $result = $swarm->run(
            agent: $agent,
            messages: [['role' => 'user', 'content' => 'start loop']],
            maxTurns: 3
        );

        $this->assertSame(3, $result->turns);
        $this->assertTrue($result->isMaxTurnsReached());
        $this->assertFalse($result->isCompleted());
    }

    public function testDynamicInstructionsInSwarm(): void
    {
        /** @var OpenAIClient&Mockery\MockInterface $mockClient */
        $mockClient = Mockery::mock(OpenAIClient::class);

        $mockClient->shouldReceive('chatCompletions')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                // Ensure system message contains dynamic context
                return isset($payload['messages'][0]['content'])
                    && str_contains($payload['messages'][0]['content'], 'VIP-999');
            }))
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Welcome VIP!',
                        ],
                    ],
                ],
            ]);

        $agent = new Agent(
            name: 'VipAgent',
            instructions: fn (array $ctx) => "System prompt with user {$ctx['tier']}"
        );

        $swarm = new Swarm(client: $mockClient);
        $result = $swarm->run(
            agent: $agent,
            messages: [['role' => 'user', 'content' => 'hello']],
            contextVariables: ['tier' => 'VIP-999']
        );

        $this->assertSame('Welcome VIP!', $result->getContent());
        $this->assertTrue($result->isCompleted());
    }

    public function testLlmApiExceptionOnMalformedResponse(): void
    {
        /** @var OpenAIClient&Mockery\MockInterface $mockClient */
        $mockClient = Mockery::mock(OpenAIClient::class);

        $mockClient->shouldReceive('chatCompletions')
            ->once()
            ->andReturn([]); // empty choices

        $this->expectException(\HyperfAgents\Exception\LlmApiException::class);

        $agent = new Agent('FailAgent', 'fail');
        $swarm = new Swarm(client: $mockClient);
        $swarm->run($agent, [['role' => 'user', 'content' => 'hi']]);
    }
}
