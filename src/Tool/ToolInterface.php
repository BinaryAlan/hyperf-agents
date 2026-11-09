<?php

declare(strict_types=1);

namespace HyperfAgents\Tool;

interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function getParameters(): array;

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $contextVariables
     */
    public function execute(array $arguments, array $contextVariables = []): mixed;
}
