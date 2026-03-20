<?php

namespace App\Mcp;

use App\Ai\Tools\SearchTool;
use App\Ai\Tools\AgentTool;
use App\Ai\Tools\ResourceTool;
use Illuminate\Support\Collection;

class McpRegistry
{
    /**
     * @return Collection<string, \Laravel\Mcp\Server\Tool>
     */
    public static function getTools(): Collection
    {
        return collect([
            'search' => new SearchTool(),
            'agent' => new AgentTool(),
            'resource' => new ResourceTool(),
        ]);
    }
}
