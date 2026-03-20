<?php

namespace App\Services\Agents;

use App\Mcp\McpRegistry;
use App\Models\Run;

class AgentFactory
{
    public static function create(Run $run): mixed
    {
        $class = McpRegistry::getAgentClass($run->agent_type);
        return new $class();
    }
}
