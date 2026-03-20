<?php

namespace App\Providers;

use App\Ai\Agents\NeuronAgent;
use App\Ai\Agents\ResearcherAgent;
use App\Ai\Tools\AgentTool;
use App\Ai\Tools\ResourceTool;
use App\Ai\Tools\SearchTool;
use App\Mcp\McpRegistry;
use App\Mcp\Servers\NeuronServer;
use App\Services\LLM\AiSdkService;
use App\Services\LLM\LLMServiceInterface;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LLMServiceInterface::class, AiSdkService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        McpRegistry::registerAgent('researcher', ResearcherAgent::class);
        McpRegistry::registerAgent('writer', NeuronAgent::class);

        McpRegistry::registerTool('search', SearchTool::class);
        McpRegistry::registerTool('agent', AgentTool::class);
        McpRegistry::registerTool('resource', ResourceTool::class);

        Mcp::local('neuron', NeuronServer::class);
    }
}
