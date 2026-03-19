<?php

namespace App\Providers;

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
        Mcp::local('neuron', NeuronServer::class);
    }
}
