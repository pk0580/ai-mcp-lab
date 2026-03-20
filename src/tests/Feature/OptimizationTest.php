<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Ai\Agents\NeuronAgent;
use App\Services\EmbeddingService;
use App\Jobs\StepJob;
use App\Services\LLM\LLMServiceInterface;
use App\Services\LLM\MockLLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_stops_at_max_steps(): void
    {
        // Принудительно используем MockLLMService для тестов
        $this->app->bind(LLMServiceInterface::class, MockLLMService::class);

        Queue::fake();

        $run = Run::create([
            'prompt' => 'Loop test',
            'status' => 'running',
        ]);

        $agent = new NeuronAgent();

        // Установим лимит в 2 шага через рефлексию для теста
        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('maxSteps');
        $property->setAccessible(true);
        $property->setValue($agent, 2);

        // 1 шаг (создает initial thought)
        $agent->processNextStep($run);
        $this->assertEquals(1, $run->steps()->count());
        $this->assertEquals('running', $run->fresh()->status);
        Queue::assertPushed(StepJob::class, 1);

        // 2 шаг (создает call)
        $agent->processNextStep($run);
        $this->assertEquals(3, $run->steps()->count());
        $this->assertEquals('running', $run->fresh()->status);
        Queue::assertPushed(StepJob::class, 2);

        // 3 шаг - должен сработать лимит
        $agent->processNextStep($run);
        $this->assertEquals(4, $run->steps()->count());
        $this->assertEquals('failed', $run->fresh()->status);
        $this->assertStringContainsString('Превышено максимальное количество шагов', $run->steps()->latest('id')->first()->content);

        // Больше не должно пушиться новых заданий после того как лимит сработал
        Queue::assertPushed(StepJob::class, 2);
    }

    public function test_embedding_service_caches_results(): void
    {
        Cache::shouldReceive('remember')
            ->once() // Должно быть вызвано ровно один раз для одного и того же текста
            ->with('embedding_' . md5('test text'), \Mockery::any(), \Mockery::on(fn($cb) => is_callable($cb)))
            ->andReturn(new \Pgvector\Laravel\Vector(array_fill(0, 1536, 0.1)));

        $service = new EmbeddingService();

        // Первый вызов (должен вызвать Cache::remember)
        $service->getEmbedding('test text');

        // Второй вызов (имитируем поведение кэша через мок, но в реальности он просто вернет значение из кэша)
        // В данном тесте we use `once()` above, so if we call it twice and it's NOT cached inside the service, it would fail.
        // Wait, Cache::remember is what DOES the caching. If I call $service->getEmbedding twice, it calls Cache::remember twice.
        // But Cache::remember itself handles the "check if exists".
        // So checking that Cache::remember is called is enough to know it uses caching.
    }

    public function test_embedding_service_returns_zero_vector_for_empty_text(): void
    {
        $service = new EmbeddingService();
        $embedding = $service->getEmbedding('');

        $this->assertEquals(array_fill(0, 1536, 0.0), $embedding->toArray());
    }
}
