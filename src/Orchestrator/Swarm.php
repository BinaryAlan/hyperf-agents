<?php

declare(strict_types=1);

namespace HyperfAgents\Orchestrator;

use HyperfAgents\Agent\AgentInterface;
use HyperfAgents\Agent\Handoff;
use HyperfAgents\Client\OpenAIClient;
use HyperfAgents\Tool\ConcurrentToolExecutorInterface;
use HyperfAgents\Tool\ConcurrentToolExecutor;
use RuntimeException;

class Swarm implements SwarmInterface
{
    protected OpenAIClient $client;

    protected ConcurrentToolExecutorInterface $toolExecutor;

    public function __construct(
        ?OpenAIClient $client = null,
        ?ConcurrentToolExecutorInterface $toolExecutor = null
    ) {
        $this->client = $client ?? new OpenAIClient();
        $this->toolExecutor = $toolExecutor ?? new ConcurrentToolExecutor();
    }

    /**
     * Run the agent loop with coroutine-accelerated tool execution and handoffs.
     *
     * @param AgentInterface $agent
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $contextVariables
     * @param int $maxTurns
     * @return RunResult
     */
    public function run(
        AgentInterface $agent,
        array $messages,
        array $contextVariables = [],
        int $maxTurns = 10
    ): RunResult {
        $activeAgent = $agent;
        $history = $messages;
        $turns = 0;
        $startTime = microtime(true);

        while ($turns < $maxTurns) {
            ++$turns;

            // 1. Build messages payload with current agent's instructions
            $payloadMessages = $this->buildMessages($activeAgent, $history);

            // 2. Format tool definitions for OpenAI
            $toolsPayload = $this->buildToolsPayload($activeAgent);

            $payload = [
                'model' => $activeAgent->getModel(),
                'messages' => $payloadMessages,
            ];

            if (! empty($toolsPayload)) {
                $payload['tools'] = $toolsPayload;
                $payload['tool_choice'] = 'auto';
            }

            // 3. Invoke OpenAI via coroutine HTTP client
            $response = $this->client->chatCompletions($payload);
            $choice = $response['choices'][0]['message'] ?? null;

            if (! $choice) {
                throw new RuntimeException('Empty or malformed completion response from OpenAI.');
            }

            $history[] = $choice;

            // 4. Check if tools need to be executed
            $toolCalls = $choice['tool_calls'] ?? [];
            if (empty($toolCalls)) {
                // No tools called; loop ends with final text reply
                break;
            }

            // 5. Execute all tool calls CONCURRENTLY using Hyperf coroutines
            $toolResults = $this->toolExecutor->executeAll(
                $toolCalls,
                $activeAgent->getTools(),
                $contextVariables
            );

            // 6. Check for handoff and append tool message results
            foreach ($toolResults as $result) {
                $history[] = [
                    'role' => 'tool',
                    'tool_call_id' => $result->toolCallId,
                    'name' => $result->toolName,
                    'content' => $result->toMessageContent(),
                ];

                if ($result->isHandoff()) {
                    $handoff = $result->getHandoff();
                    if ($handoff instanceof Handoff) {
                        $activeAgent = $handoff->getTargetAgent();
                        $contextVariables = array_merge($contextVariables, $handoff->getContextVariables());
                    }
                }
            }
        }

        $elapsed = (microtime(true) - $startTime) * 1000;

        return new RunResult(
            messages: $history,
            lastAgent: $activeAgent,
            contextVariables: $contextVariables,
            turns: $turns,
            totalLatencyMs: $elapsed
        );
    }

    protected function buildMessages(AgentInterface $agent, array $history): array
    {
        $systemMessage = [
            'role' => 'system',
            'content' => $agent->getInstructions(),
        ];

        return array_merge([$systemMessage], $history);
    }

    protected function buildToolsPayload(AgentInterface $agent): array
    {
        $payload = [];
        foreach ($agent->getTools() as $name => $meta) {
            $payload[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $meta['description'],
                    'parameters' => $meta['parameters'],
                ],
            ];
        }

        return $payload;
    }
}
