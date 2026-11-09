<?php

declare(strict_types=1);

namespace HyperfAgents\Orchestrator;

use HyperfAgents\Agent\AgentInterface;
use HyperfAgents\Agent\Handoff;
use HyperfAgents\Client\OpenAIClient;
use HyperfAgents\Client\OpenAIClientInterface;
use HyperfAgents\Config\AgentConfig;
use HyperfAgents\Exception\LlmApiException;
use HyperfAgents\Resilience\CircuitBreaker;
use HyperfAgents\Tool\ConcurrentToolExecutor;
use HyperfAgents\Tool\ConcurrentToolExecutorInterface;

class Swarm implements SwarmInterface
{
    protected OpenAIClientInterface $client;

    protected ConcurrentToolExecutorInterface $toolExecutor;

    public function __construct(
        ?OpenAIClientInterface $client = null,
        ?ConcurrentToolExecutorInterface $toolExecutor = null,
        protected ?AgentConfig $config = null
    ) {
        $this->client = $client ?? new OpenAIClient();
        $this->toolExecutor = $toolExecutor ?? new ConcurrentToolExecutor();
    }

    public function getConfig(): ?AgentConfig
    {
        return $this->config;
    }

    /**
     * @param AgentConfig|array<string, mixed> $config
     */
    public static function fromConfig(AgentConfig|array $config): self
    {
        $agentConfig = $config instanceof AgentConfig ? $config : AgentConfig::fromArray($config);

        $driver = $agentConfig->getDriverConfig();
        $apiKey = $driver['api_key'];
        $baseUrl = $driver['base_url'];

        /** @var array<string, mixed> $clientOptions */
        $clientOptions = $driver['client_options'];
        $clientOptions['timeout'] = $driver['timeout'];

        $retry = $agentConfig->getRetryConfig();
        $maxAttempts = 3;
        $initialDelayMs = 200;
        $multiplier = 2.0;

        if ($retry['enabled']) {
            $maxAttempts = $retry['max_attempts'];
            $initialDelayMs = $retry['initial_delay_ms'];
            $multiplier = $retry['multiplier'];
        } else {
            $maxAttempts = 1;
        }

        $cbConfig = $agentConfig->getCircuitBreakerConfig();
        $circuitBreaker = null;

        if ($cbConfig['enabled']) {
            $circuitBreaker = new CircuitBreaker(
                failureThreshold: $cbConfig['failure_threshold'],
                recoverySeconds: $cbConfig['recovery_time']
            );
        }

        $client = new OpenAIClient(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            container: null,
            clientOptions: $clientOptions,
            maxAttempts: $maxAttempts,
            initialDelayMs: $initialDelayMs,
            multiplier: $multiplier,
            sleeper: null,
            circuitBreaker: $circuitBreaker
        );

        $executor = new ConcurrentToolExecutor(
            maxConcurrency: $agentConfig->getMaxToolConcurrency()
        );

        return new self($client, $executor, $agentConfig);
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
        $status = RunStatus::MAX_TURNS_REACHED;

        while ($turns < $maxTurns) {
            ++$turns;

            // 1. Build messages payload with current agent's instructions (injected with context)
            $payloadMessages = $this->buildMessages($activeAgent, $history, $contextVariables);

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
                throw new LlmApiException('Empty or malformed completion response from OpenAI.', 500, null, $payload);
            }

            $history[] = $choice;

            // 4. Check if tools need to be executed
            $toolCalls = $choice['tool_calls'] ?? [];
            if (empty($toolCalls)) {
                // No tools called; loop ends with final text reply
                $status = RunStatus::COMPLETED;
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
            totalLatencyMs: $elapsed,
            status: $status
        );
    }

    /**
     * @param array<int, array<string, mixed>> $history
     * @param array<string, mixed> $contextVariables
     * @return array<int, array<string, mixed>>
     */
    protected function buildMessages(AgentInterface $agent, array $history, array $contextVariables = []): array
    {
        $systemMessage = [
            'role' => 'system',
            'content' => $agent->getInstructions($contextVariables),
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
