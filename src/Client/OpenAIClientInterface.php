<?php

declare(strict_types=1);

namespace HyperfAgents\Client;

interface OpenAIClientInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function chatCompletions(array $payload): array;
}
