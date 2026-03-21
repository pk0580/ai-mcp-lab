<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Models\Step;
use App\Ai\Agents\NeuronAgent;
use App\Services\LLM\LLMServiceInterface;
use App\Ai\Tools\SearchTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class NeuronAgentIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_continues_after_info_step(): void
    {
        // Отключаем очереди для синхронного выполнения в тесте
        \Illuminate\Support\Facades\Queue::fake();

        // 1. Мокаем LLM сервис, чтобы он возвращал сначала мысль, потом ответ
        $llmMock = Mockery::mock(LLMServiceInterface::class);
        $this->app->instance(LLMServiceInterface::class, $llmMock);

        $run = Run::create([
            'prompt' => 'Расскажи про процесс авторизации при подключении пользователя в Hello Payment Solutions',
            'status' => 'pending',
            'agent_type' => 'neuron', // Указываем тип агента, чтобы AgentFactory работал
        ]);

        $agent = new NeuronAgent([
            'search' => new SearchTool(),
        ]);

        // Ожидаем два вызова generateNextStep
        $llmMock->shouldReceive('generateNextStep')
            ->once()
            ->andReturn([
                'type' => 'thought',
                'content' => 'Мне нужно найти информацию о процессе авторизации в Hello Payment Solutions.',
                'metadata' => []
            ]);

        $llmMock->shouldReceive('generateNextStep')
            ->once()
            ->andReturn([
                'type' => 'answer',
                'content' => 'Процесс авторизации включает...',
                'metadata' => []
            ]);

        // Выполняем первый проход (должен создать Info и Thought)
        $agent->processNextStep($run);

        // stepsCount был 0 -> создается Info ("Начинаю обработку...")
        // вызывается generateNextStep -> возвращает Thought -> создается Thought шаг
        $steps = $run->fresh()->steps()->orderBy('id')->get();
        $this->assertCount(2, $steps);
        $this->assertEquals('info', $steps[0]->type);
        $this->assertEquals('thought', $steps[1]->type);
        $this->assertEquals('running', $run->fresh()->status);

        // Выполняем второй проход (должен создать Answer)
        $agent->processNextStep($run);

        // stepsCount был 2 -> Info НЕ создается
        // вызывается generateNextStep -> возвращает Answer -> создается Answer шаг
        $steps = $run->fresh()->steps()->orderBy('id')->get();
        $this->assertCount(3, $steps);
        $this->assertEquals('answer', $steps[2]->type);
        $this->assertEquals('completed', $run->fresh()->status);
    }

    public function test_agent_handles_empty_response_from_llm(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $llmMock = Mockery::mock(LLMServiceInterface::class);
        $this->app->instance(LLMServiceInterface::class, $llmMock);

        $run = Run::create([
            'prompt' => 'Empty response test',
            'status' => 'pending',
            'agent_type' => 'neuron',
        ]);

        $agent = new NeuronAgent();

        // Имитируем ситуацию, когда сервис вернул ошибку из-за пустого ответа
        $llmMock->shouldReceive('generateNextStep')
            ->once()
            ->andReturn([
                'type' => 'error',
                'content' => 'Модель вернула пустой ответ. Пожалуйста, попробуйте еще раз или уточните запрос.',
                'metadata' => []
            ]);

        $agent->processNextStep($run);

        // 1. Создан Info шаг ("Начинаю обработку...") т.к. stepsCount был 0
        // 2. Создан Error шаг
        $steps = $run->fresh()->steps()->orderBy('id')->get();
        $this->assertCount(2, $steps);
        $this->assertEquals('info', $steps[0]->type);
        $this->assertEquals('error', $steps[1]->type);
        $this->assertEquals('failed', $run->fresh()->status);
    }
}
