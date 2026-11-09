<?php

declare(strict_types=1);

namespace HyperfAgents\Orchestrator;

use HyperfAgents\Agent\AgentInterface;

class RunResult
{
    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $contextVariables
     */
    public function __construct(
        public array $messages,
        public AgentInterface $lastAgent,
        public array $contextVariables = [],
        public int $turns = 0,
        public float $totalLatencyMs = 0.0,
        public RunStatus $status = RunStatus::COMPLETED
    ) {
    }

    public function isCompleted(): bool
    {
        return $this->status === RunStatus::COMPLETED;
    }

    public function isMaxTurnsReached(): bool
    {
        return $this->status === RunStatus::MAX_TURNS_REACHED;
    }

    public function getLastMessage(): ?array
    {
        return empty($this->messages) ? null : end($this->messages);
    }

    public function getContent(): string
    {
        $last = $this->getLastMessage();
        return $last['content'] ?? '';
    }
}
