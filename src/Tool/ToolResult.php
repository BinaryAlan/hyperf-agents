<?php

declare(strict_types=1);

namespace HyperfAgents\Tool;

use HyperfAgents\Agent\Handoff;

class ToolResult
{
    public function __construct(
        public string $toolCallId,
        public string $toolName,
        public mixed $output,
        public ?Handoff $handoff = null,
        public bool $success = true,
        public ?string $errorMessage = null,
        public float $executionTimeMs = 0.0
    ) {
    }

    public function isHandoff(): bool
    {
        return $this->handoff !== null || $this->output instanceof Handoff;
    }

    public function getHandoff(): ?Handoff
    {
        if ($this->handoff) {
            return $this->handoff;
        }

        if ($this->output instanceof Handoff) {
            return $this->output;
        }

        return null;
    }

    public function toMessageContent(): string
    {
        $handoff = $this->getHandoff();
        if ($handoff !== null) {
            return 'Transferred to agent: ' . $handoff->getTargetAgent()->getName();
        }

        if (is_string($this->output)) {
            return $this->output;
        }

        return json_encode($this->output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
