<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\ErrorPrompt;
use App\Mcp\Prompts\SystemPrompt;
use App\Mcp\Resources\MemoryResource;
use App\Mcp\Resources\ProjectResource;
use App\Mcp\McpRegistry;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ServerContext;

#[Name('Neuron Server')]
#[Version('0.1.0')]
#[Instructions('Neuron MCP Server provides tools for reasoning, delegation and memory retrieval.')]
class NeuronServer extends Server
{
    public function createContext(): ServerContext
    {
        $this->tools = McpRegistry::getTools()->map(function ($tool) {
            return get_class($tool);
        })->values()->toArray();

        return parent::createContext();
    }

    protected array $resources = [
        ProjectResource::class,
        MemoryResource::class,
    ];

    protected array $prompts = [
        SystemPrompt::class,
        ErrorPrompt::class,
    ];
}
