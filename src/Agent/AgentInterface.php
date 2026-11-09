<?php

declare(strict_types=1);

namespace HyperfAgents\Agent;

use HyperfAgents\Tool\ToolInterface;

interface AgentInterface
{
    public function getName(): string;

    public function getModel(): string;

    /**
     * @param array<string, mixed> $contextVariables
     */
    public function getInstructions(array $contextVariables = []): string;

    /**
     * @return array<string, array{handler: ToolInterface|callable, description: string, parameters: array}>
     */
    public function getTools(): array;

    public function addTool(string $name, ToolInterface|callable $tool, ?string $description = null, array $parameters = []): static;

    /**
     * Fluent helper to add an agent-to-agent handoff.
     *
     * @param array<string, mixed> $contextVariables
     */
    public function addHandoff(AgentInterface $targetAgent, ?string $name = null, ?string $description = null, array $contextVariables = []): static;

    public function toArray(): array;
}
