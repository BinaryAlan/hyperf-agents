<?php

declare(strict_types=1);

namespace HyperfAgents\Exception;

class ToolExecutionException extends AgentException
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        protected string $toolName,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        protected array $arguments = []
    ) {
        parent::__construct("Tool [{$toolName}] execution failed: {$message}", $code, $previous);
    }

    public function getToolName(): string
    {
        return $this->toolName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
