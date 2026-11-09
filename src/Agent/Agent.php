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

    public function __construct(
        protected string $name,
        protected string $instructions,
        protected string $model = 'gpt-4o',
        array $tools = []
    ) {
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

    public function getInstructions(): string
    {
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

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'model' => $this->model,
            'instructions' => $this->instructions,
            'tools_count' => count($this->tools),
        ];
    }
}
