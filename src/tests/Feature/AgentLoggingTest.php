<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Models\AgentStep;
use App\Services\Agents\NeuronAgent;
use App\Services\LLM\LLMServiceInterface;
use App\Services\LLM\MockLLMService;
use App\Services\Tools\SearchTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgentLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_logs_steps_and_latency(): void
    {
        // Принудительно используем MockLLMService для тестов
        $this->app->bind(LLMServiceInterface::class, MockLLMService::class);

        Queue::fake();

        $run = Run::create([
            'prompt' => 'Test with logging',
            'status' => 'pending',
        ]);

        $agent = new NeuronAgent([
            new SearchTool(),
        ]);

        // Первый шаг: создается Thought
        $agent->processNextStep($run);

        // Проверяем, что создана запись в agent_steps
        $this->assertDatabaseHas('agent_steps', [
            'run_id' => $run->id,
            'category' => 'step_creation',
            'level' => 'info',
        ]);

        $log = AgentStep::where('run_id', $run->id)->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->step_id);
        // Проверяем наличие задержки (в миллисекундах)
        $this->assertIsInt($log->latency_ms);
        $this->assertGreaterThanOrEqual(0, $log->latency_ms);
    }

    public function test_api_returns_logs(): void
    {
        $run = Run::create([
            'prompt' => 'API Log test',
            'status' => 'completed',
        ]);

        $step = $run->steps()->create([
            'type' => 'thought',
            'content' => 'Thinking...',
        ]);

        AgentStep::create([
            'run_id' => $run->id,
            'step_id' => $step->id,
            'level' => 'info',
            'category' => 'test',
            'message' => 'Test log message',
            'latency_ms' => 123,
        ]);

        $response = $this->getJson("/api/runs/{$run->id}/logs");

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.message', 'Test log message')
            ->assertJsonPath('0.latency_ms', 123);
    }
}
