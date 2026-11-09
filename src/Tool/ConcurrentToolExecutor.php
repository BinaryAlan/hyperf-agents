<?php

declare(strict_types=1);

namespace HyperfAgents\Tool;

use Hyperf\Coroutine\Coroutine;
use HyperfAgents\Agent\Handoff;
use Throwable;

use function Hyperf\Coroutine\parallel;

class ConcurrentToolExecutor implements ConcurrentToolExecutorInterface
{
    public function __construct(
        protected int $maxConcurrency = 20
    ) {
    }

    public function executeAll(array $toolCalls, array $tools, array $contextVariables = []): array
    {
        if (empty($toolCalls)) {
            return [];
        }

        // If only 1 tool call or coroutine environment is not active, execute directly
        if (count($toolCalls) === 1 || ! Coroutine::inCoroutine()) {
            return array_map(
                fn ($call) => $this->executeSingle($call, $tools, $contextVariables),
                $toolCalls
            );
        }

        // Dispatch all tool executions concurrently via Hyperf coroutine parallel helper
        $callables = [];
        foreach ($toolCalls as $index => $call) {
            $callables[$index] = fn () => $this->executeSingle($call, $tools, $contextVariables);
        }

        /** @var array<int, ToolResult> $results */
        $results = parallel($callables, $this->maxConcurrency);
        ksort($results);

        return $results;
    }

    protected function executeSingle(array $toolCall, array $tools, array $contextVariables): ToolResult
    {
        $id = $toolCall['id'] ?? uniqid('call_');
        $name = $toolCall['function']['name'] ?? '';
        $rawArgs = $toolCall['function']['arguments'] ?? '{}';
        $startTime = microtime(true);

        if (! isset($tools[$name])) {
            return new ToolResult(
                toolCallId: $id,
                toolName: $name,
                output: "Error: Tool '{$name}' not found on active agent.",
                success: false,
                errorMessage: "Tool '{$name}' not registered."
            );
        }

        $arguments = json_decode($rawArgs, true);
        if (! is_array($arguments)) {
            $arguments = [];
        }

        $handler = $tools[$name]['handler'];

        try {
            if ($handler instanceof ToolInterface) {
                $output = $handler->execute($arguments, $contextVariables);
            } elseif (is_callable($handler)) {
                $output = $handler($arguments, $contextVariables);
            } else {
                throw new \InvalidArgumentException("Invalid tool handler for {$name}");
            }

            $elapsed = (microtime(true) - $startTime) * 1000;

            return new ToolResult(
                toolCallId: $id,
                toolName: $name,
                output: $output,
                handoff: $output instanceof Handoff ? $output : null,
                success: true,
                executionTimeMs: $elapsed
            );
        } catch (Throwable $e) {
            $elapsed = (microtime(true) - $startTime) * 1000;
            return new ToolResult(
                toolCallId: $id,
                toolName: $name,
                output: 'Execution failed: ' . $e->getMessage(),
                success: false,
                errorMessage: $e->getMessage(),
                executionTimeMs: $elapsed
            );
        }
    }
}
