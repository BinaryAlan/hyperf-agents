<?php

declare(strict_types=1);

namespace HyperfAgents\Orchestrator;

use HyperfAgents\Agent\AgentInterface;

interface SwarmInterface
{
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
    ): RunResult;
}
