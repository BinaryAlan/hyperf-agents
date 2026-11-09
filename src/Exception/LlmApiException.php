<?php

declare(strict_types=1);

namespace HyperfAgents\Exception;

class LlmApiException extends AgentException
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        protected array $payload = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
