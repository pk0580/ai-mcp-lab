<?php

namespace Tests\Feature;

use App\Jobs\StepJob;
use App\Models\Run;
use App\Models\Step;
use App\Ai\Agents\NeuronAgent;
use App\Services\LLM\LLMServiceInterface;
use App\Services\LLM\MockLLMService;
use App\Ai\Tools\SearchTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NeuronAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_creates_memory_embeddings(): void
    {
        // Принудительно используем MockLLMService для тестов
        $this->app->bind(LLMServiceInterface::class, MockLLMService::class);

        $run = Run::create([
            'prompt' => 'Tell me about memory',
            'status' => 'pending',
        ]);

        $agent = new NeuronAgent();

        // 1. Создаем шаг
        $agent->processNextStep($run);

        $step = $run->steps()->first();
        $this->assertNotNull($step);
        $this->assertNotNull($step->embedding);
        $this->assertNotNull($step->embedding->embedding);
    }

    public function test_agent_can_retrieve_from_memory(): void
    {
        $run = Run::create([
            'prompt' => 'Memory test',
            'status' => 'pending',
        ]);

        $agent = new NeuronAgent();

        // Создаем несколько шагов с разным контентом
        $step1 = $run->steps()->create(['type' => 'thought', 'content' => 'The sky is blue']);
        $agent->retrieveFromMemory('dummy'); // just to use agent's logic for embeddings if needed, but we create manually below to be sure

        // На самом деле createStep в агенте делает эмбеддинги. Используем его.
        // Мы не можем вызвать напрямую protected, но можем через processNextStep или рефлексию.
        // Или просто протестируем retrieveFromMemory после того как шаги созданы агентом.

        // Очистим и создадим через агента (имитируем внутренний вызов через public обертку или просто доверяем тесту выше)
        Step::truncate();

        // Используем Reflection для доступа к protected методу для чистоты теста памяти
        $reflection = new \ReflectionClass(NeuronAgent::class);
        $method = $reflection->getMethod('createStep');
        $method->setAccessible(true);

        $method->invoke($agent, $run, 'thought', 'Information about artificial intelligence');
        $method->invoke($agent, $run, 'thought', 'Information about cooking pasta');

        // Ищем что-то похожее на AI
        $results = $agent->retrieveFromMemory('AI and machine learning', 1);

        $this->assertCount(1, $results);
        $this->assertStringContainsString('artificial intelligence', $results[0]->content);
    }

    public function test_agent_creates_steps_on_run(): void
    {
        // Принудительно используем MockLLMService для тестов
        $this->app->bind(LLMServiceInterface::class, MockLLMService::class);

        Queue::fake();

        $run = Run::create([
            'prompt' => 'What is a multi-agent system?',
            'status' => 'pending',
        ]);

        $agent = new NeuronAgent([
            'search' => new SearchTool(),
        ]);

        $agent->run($run);

        $this->assertEquals('running', $run->fresh()->status);
        Queue::assertPushed(StepJob::class);
    }

    public function test_agent_processes_steps_asynchronously(): void
    {
        // Принудительно используем MockLLMService для тестов
        $this->app->bind(LLMServiceInterface::class, MockLLMService::class);

        Queue::fake();

        $run = Run::create([
            'prompt' => 'What is a multi-agent system?',
            'status' => 'pending',
        ]);

        $agent = new NeuronAgent([
            'search' => new SearchTool(),
        ]);

        // Имитируем запуск через run
        $run->update(['status' => 'running']);

        // 1. Первый шаг: Thought
        $agent->processNextStep($run);
        $this->assertCount(1, $run->refresh()->steps);
        $this->assertEquals('thought', $run->steps->last()->type);
        Queue::assertPushed(StepJob::class, 1);

        // 2. Второй шаг: Call и Observation создаются вместе в новом NeuronAgent
        $agent->processNextStep($run);
        $this->assertCount(3, $run->refresh()->steps); // Thought + Call + Observation
        $this->assertEquals('observation', $run->steps->last()->type);
        $this->assertEquals('call', $run->steps[1]->type);
        Queue::assertPushed(StepJob::class, 2);

        // 3. Третий шаг: Reflection
        $agent->processNextStep($run);
        $this->assertCount(4, $run->refresh()->steps);
        $this->assertEquals('reflection', $run->steps->last()->type);
        Queue::assertPushed(StepJob::class, 3);

        // 4. Четвертый шаг: Answer
        $agent->processNextStep($run);
        $this->assertCount(5, $run->refresh()->steps);
        $this->assertEquals('answer', $run->steps->last()->type);
        // Answer - это последний шаг, больше Job не пушится
        Queue::assertPushed(StepJob::class, 3);

        // 5. Завершение
        $agent->processNextStep($run);
        $this->assertEquals('completed', $run->fresh()->status);
        Queue::assertPushed(StepJob::class, 3);
    }
}
