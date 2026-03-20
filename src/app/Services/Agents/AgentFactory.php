<?php

namespace App\Services\Agents;

use App\Ai\Agents\NeuronAgent;
use App\Ai\Agents\ResearcherAgent;
use App\Models\Run;

class AgentFactory
{
    public static function create(Run $run): mixed
    {
        $agentType = $run->agent_type ?? 'researcher';

        return match ($agentType) {
            'researcher' => new ResearcherAgent(),
            'writer' => new NeuronAgent(),
            default => new ResearcherAgent(),
        };
    }
}
