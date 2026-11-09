<?php

declare(strict_types=1);

namespace HyperfAgents\Agent;

/**
 * Handoff represents an agent transferring execution control to another agent.
 * Compatible with OpenAI Swarm protocol.
 */
class Handoff
{
    public function __construct(
        protected AgentInterface $targetAgent,
        protected array $contextVariables = [],
        protected ?string $handoffMessage = null
    ) {
    }

    public function getTargetAgent(): AgentInterface
    {
        return $this->targetAgent;
    }

    public function getContextVariables(): array
    {
        return $this->contextVariables;
    }

    public function getHandoffMessage(): ?string
    {
        return $this->handoffMessage;
    }
}
