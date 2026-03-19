<?php

namespace App\Services\Agents;

use App\Models\Run;

class AgentFactory
{
    public static function create(Run $run): AgentInterface
    {
        $agentType = $run->agent_type ?? 'researcher';

        $agent = match ($agentType) {
            'researcher' => new ResearcherAgent(),
            'writer' => new NeuronAgent(),
            default => new ResearcherAgent(),
        };

        // Инструменты по умолчанию уже добавляются в конструкторе NeuronAgent через McpRegistry.
        // Но если нужно добавить специфические инструменты:
        // $agent->addTool('search', new SearchTool());

        return $agent;
    }
}
