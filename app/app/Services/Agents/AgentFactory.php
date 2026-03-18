<?php

namespace App\Services\Agents;

use App\Models\Run;
use App\Services\Tools\SearchTool;
use App\Services\Tools\AgentTool;

class AgentFactory
{
    public static function create(Run $run): AgentInterface
    {
        $agentType = $run->agent_type ?? 'researcher';

        $agent = match ($agentType) {
            'researcher' => new ResearcherAgent(),
            'writer' => new WriterAgent(),
            default => new ResearcherAgent(),
        };

        // Добавляем инструменты
        $agent->addTool(new SearchTool());
        $agent->addTool(new AgentTool());

        return $agent;
    }
}
