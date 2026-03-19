<?php

namespace App\Mcp;

use App\Mcp\Tools\AgentTool;
use App\Mcp\Tools\ResourceTool;
use App\Mcp\Tools\SearchTool;
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
