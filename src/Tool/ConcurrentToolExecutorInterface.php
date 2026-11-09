<?php

declare(strict_types=1);

namespace HyperfAgents\Tool;

interface ConcurrentToolExecutorInterface
{
    /**
     * @param array<int, array{id: string, function: array{name: string, arguments: string}}> $toolCalls
     * @param array<string, array{handler: ToolInterface|callable}> $tools
     * @param array<string, mixed> $contextVariables
     * @return array<int, ToolResult>
     */
    public function executeAll(array $toolCalls, array $tools, array $contextVariables = []): array;
}
