<?php

declare(strict_types=1);

namespace HyperfAgents\Agent;

use HyperfAgents\Tool\ToolInterface;

class Agent implements AgentInterface
{
    /**
     * @var array<string, array{handler: ToolInterface|callable, description: string, parameters: array}>
     */
    protected array $tools = [];

    protected string|\Closure $instructions;

    /**
     * @param array<string, ToolInterface|callable> $tools
     */
    public function __construct(
        protected string $name,
        string|callable $instructions,
        protected string $model = 'gpt-4o',
        array $tools = []
    ) {
        $this->instructions = is_callable($instructions) ? \Closure::fromCallable($instructions) : $instructions;

        foreach ($tools as $name => $tool) {
            if ($tool instanceof ToolInterface) {
                $this->addTool($tool->getName(), $tool, $tool->getDescription(), $tool->getParameters());
            } elseif (is_callable($tool)) {
                $this->addTool((string)$name, $tool);
            }
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getInstructions(array $contextVariables = []): string
    {
        if ($this->instructions instanceof \Closure) {
            return (string) ($this->instructions)($contextVariables);
        }

        return $this->instructions;
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function addTool(string $name, ToolInterface|callable $tool, ?string $description = null, array $parameters = []): static
    {
        $this->tools[$name] = [
            'handler' => $tool,
            'description' => $description ?? "Tool: {$name}",
            'parameters' => $parameters ?: [
                'type' => 'object',
                'properties' => new \stdClass(),
            ],
        ];

        return $this;
    }

    public function addHandoff(
        AgentInterface $targetAgent,
        ?string $name = null,
        ?string $description = null,
        array $contextVariables = []
    ): static {
        $defaultName = 'transfer_to_' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $targetAgent->getName()));
        $toolName = $name ?: $defaultName;
        $toolDesc = $description ?: "Transfer conversation to {$targetAgent->getName()}.";

        return $this->addTool(
            name: $toolName,
            tool: fn () => new Handoff($targetAgent, $contextVariables),
            description: $toolDesc
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'model' => $this->model,
            'instructions' => $this->instructions instanceof \Closure ? '[Dynamic Instructions]' : $this->instructions,
            'tools_count' => count($this->tools),
        ];
    }
}
