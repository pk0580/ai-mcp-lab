<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Services\Agents\NeuronAgent;
use App\Services\LLM\LLMServiceInterface;
use App\Services\LLM\MockLLMService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReflectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_handles_tool_failure_and_retries(): void
    {
        // Принудительно используем MockLLMService для тестов
        $this->app->bind(LLMServiceInterface::class, MockLLMService::class);

        Queue::fake();

        $run = Run::create([
            'prompt' => 'Test retry logic',
            'status' => 'running',
        ]);

        // Инструмент, который падает в первый раз, но работает во второй (имитация)
        $failingTool = new class extends Tool {
            public static int $calls = 0;
            public function handle(Request $request): Response {
                self::$calls++;
                if (self::$calls === 1) {
                    throw new \Exception("Temporary failure");
                }
                return Response::text("Success on try " . self::$calls);
            }
        };

        $agent = new NeuronAgent(['failing_tool' => $failingTool], null, new MockLLMService());

        // 1. Создаем шаг-мысль, чтобы LLM решила вызвать инструмент
        $run->steps()->create([
            'type' => 'thought',
            'content' => 'Test retry logic',
        ]);

        // Выполняем шаг. Агент должен вызвать инструмент, поймать ошибку и создать шаг 'error'
        $agent->processNextStep($run);

        $lastStep = $run->steps()->latest('id')->first();
        $this->assertEquals('error', $lastStep->type);

        // 2. Выполняем следующий проход. Агент должен увидеть ошибку и попробовать еще раз (retry)
        $agent->processNextStep($run);

        $lastStep = $run->steps()->latest('id')->first();
        $this->assertEquals('observation', $lastStep->type);
        $this->assertStringContainsString('Success on try 2', $lastStep->content);
    }

    public function test_agent_performs_reflection_before_answering(): void
    {
        // Принудительно используем MockLLMService для тестов
        $this->app->bind(LLMServiceInterface::class, MockLLMService::class);

        Queue::fake();

        $run = Run::create([
            'prompt' => 'Test reflection',
            'status' => 'running',
        ]);

        $agent = new NeuronAgent([], null, new MockLLMService());

        // Имитируем получение наблюдения
        $run->steps()->create([
            'type' => 'observation',
            'content' => 'Found some data, but it might be incomplete.',
        ]);

        // Агент должен выполнить 'reflection' вместо перехода сразу к 'answer'
        $agent->processNextStep($run);

        $lastStep = $run->steps()->latest('id')->first();
        $this->assertEquals('reflection', $lastStep->type);
        $this->assertStringContainsString('Анализирую полученные данные', $lastStep->content);

        // После рефлексии уже идет ответ (в нашей упрощенной имитации)
        $agent->processNextStep($run);
        $lastStep = $run->steps()->latest('id')->first();
        $this->assertEquals('answer', $lastStep->type);
    }
}
