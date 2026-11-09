<?php

declare(strict_types=1);

namespace HyperfAgents\Exception;

class MaxTurnsExceededException extends AgentException
{
    public function __construct(
        protected int $maxTurns,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $msg = $message ?: "Agent execution exceeded maximum turns limit of {$maxTurns}.";
        parent::__construct($msg, $code, $previous);
    }

    public function getMaxTurns(): int
    {
        return $this->maxTurns;
    }
}
