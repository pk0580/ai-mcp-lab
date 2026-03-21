<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Ai\Agents\NeuronAgent;
use App\Jobs\StepJob;
use App\Services\LLM\LLMServiceInterface;
use App\Services\LLM\MockLLMService;
use App\Services\MockEmbeddingService;
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

        // 1 шаг (создает info и initial thought)
        $agent->processNextStep($run);
        $this->assertEquals(2, $run->steps()->count());
        $this->assertEquals('running', $run->fresh()->status);
        Queue::assertPushed(StepJob::class, 1);

        // 2 шаг - лимит УЖЕ сработает, так как steps()->count() === 2, а maxSteps === 2
        // В NeuronAgent: if ($stepsCount >= $this->maxSteps) { ... failed }
        $agent->processNextStep($run);
        $this->assertEquals(3, $run->steps()->count());
        $this->assertEquals('failed', $run->fresh()->status);
        $this->assertStringContainsString('Превышено максимальное количество шагов', $run->steps()->latest('id')->first()->content);

        // Больше не должно пушиться новых заданий после того как лимит сработал
        Queue::assertPushed(StepJob::class, 1);
    }

    public function test_embedding_service_caches_results(): void
    {
        Cache::shouldReceive('remember')
            ->twice() // Один раз для первого вызова, один раз для второго (так как Cache::remember сам проверяет наличие)
            ->andReturn(new \Pgvector\Laravel\Vector(array_fill(0, 1536, 0.0)));

        $service = new MockEmbeddingService();

        $service->getEmbedding('test text');
        $service->getEmbedding('test text');

        $this->assertTrue(true);
    }

    public function test_embedding_service_returns_zero_vector_for_empty_text(): void
    {
        $service = new MockEmbeddingService();
        $embedding = $service->getEmbedding('');

        $this->assertEquals(array_fill(0, 1536, 0.0), $embedding->toArray());
    }
}
