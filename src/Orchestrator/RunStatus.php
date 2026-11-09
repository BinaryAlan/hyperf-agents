<?php

declare(strict_types=1);

namespace HyperfAgents\Orchestrator;

enum RunStatus: string
{
    case COMPLETED = 'completed';
    case MAX_TURNS_REACHED = 'max_turns_reached';
}
