<?php

declare(strict_types=1);

namespace HyperfAgents\Agent;

use HyperfAgents\Tool\ToolInterface;

interface AgentInterface
{
    public function getName(): string;

    public function getModel(): string;

    public function getInstructions(): string;

    /**
     * @return array<string, array{handler: ToolInterface|callable, description: string, parameters: array}>
     */
    public function getTools(): array;

    public function addTool(string $name, ToolInterface|callable $tool, ?string $description = null, array $parameters = []): static;

    public function toArray(): array;
}
