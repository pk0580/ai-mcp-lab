<?php

namespace App\Mcp;

use App\Ai\Agents\NeuronAgent;
use App\Ai\Tools\SearchTool;
use App\Ai\Tools\AgentTool;
use App\Ai\Tools\ResourceTool;
use Illuminate\Support\Collection;

class McpRegistry
{
    protected static array $agents = [];
    protected static array $tools = [];

    public static function registerAgent(string $type, string $class): void
    {
        self::$agents[$type] = $class;
    }

    public static function registerTool(string $name, string|object $tool): void
    {
        self::$tools[$name] = $tool;
    }

    public static function getAgentClass(string $type): string
    {
        return self::$agents[$type] ?? NeuronAgent::class;
    }

    /**
     * @return Collection<string, \App\Ai\Tools\ToolInterface>
     */
    public static function getTools(): Collection
    {
        $tools = collect(self::$tools);

        if ($tools->isEmpty()) {
            $tools['search'] = new SearchTool();
            $tools['agent'] = new AgentTool();
        }

        return $tools->map(function ($tool) {
            return is_string($tool) ? new $tool() : $tool;
        });
    }
}
