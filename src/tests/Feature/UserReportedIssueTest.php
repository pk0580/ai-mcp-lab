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

class UserReportedIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_reproduce_user_scenario(): void
    {
        // Отключаем очереди для синхронного выполнения в тесте
        \Illuminate\Support\Facades\Queue::fake();

        $llmMock = Mockery::mock(LLMServiceInterface::class);
        $this->app->instance(LLMServiceInterface::class, $llmMock);

        $run = Run::create([
            'prompt' => 'Расскажи про процесс авторизации при подключении пользователя в Hello Payment Solutions',
            'status' => 'pending',
            'agent_type' => 'neuron',
        ]);

        $agent = new NeuronAgent([
            'search' => new SearchTool(),
        ]);

        // 1. Агент решает вызвать инструмент, но добавляет странное обоснование
        $llmMock->shouldReceive('generateNextStep')
            ->once()
            ->andReturn([
                'type' => 'call',
                'content' => 'Из-за того, что не удалось получить необходимую информацию я попробую вызвать другой инструмент:',
                'metadata' => [
                    'tool' => 'search',
                    'args' => ['query' => 'авторизация при подключении пользователя в Hello Payment Solutions'],
                    'call_id' => 'call_1'
                ]
            ]);

        // 2. Агент получает пустой результат от поиска и решает просто завершиться или дать ответ без упоминания неудачи
        $llmMock->shouldReceive('generateNextStep')
            ->once()
            ->andReturn([
                'type' => 'answer',
                'content' => 'К сожалению, я не нашел информации.',
                'metadata' => []
            ]);

        // Выполняем первый проход (Info -> Call -> Info (tool) -> Observation)
        $agent->processNextStep($run);

        $steps = $run->fresh()->steps()->orderBy('id')->get();

        // Ожидаем:
        // 0: info (Начинаю обработку...)
        // 1: call (Из-за того, что не удалось...)
        // 2: info (Выполняю инструмент search...)
        // 3: observation ({"status":"no_results",...})

        $this->assertCount(4, $steps);
        $this->assertEquals('info', $steps[0]->type);
        $this->assertEquals('call', $steps[1]->type);
        $this->assertStringContainsString('не удалось получить необходимую информацию', $steps[1]->content);
        $this->assertEquals('info', $steps[2]->type);
        $this->assertEquals('observation', $steps[3]->type);

        // Выполняем второй проход (должен создать Answer)
        $agent->processNextStep($run);

        $steps = $run->fresh()->steps()->orderBy('id')->get();
        $this->assertCount(5, $steps);
        $this->assertEquals('answer', $steps[4]->type);
    }
}
