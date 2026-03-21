<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Ai\Agents\NeuronAgent;
use App\Services\LLM\LLMServiceInterface;
use App\Services\LLM\MockLLMService;
use Laravel\Mcp\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FailingToolStub {
    public static int $calls = 0;
    public function getName(): string { return 'failing_tool'; }
    public function handle(): Response {
        self::$calls++;
        if (self::$calls === 1) {
            throw new \Exception("Temporary failure");
        }
        return Response::text("Success on try " . self::$calls);
    }
}

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
        $failingTool = new FailingToolStub();

        $agent = new NeuronAgent(['failing_tool' => $failingTool], null, new MockLLMService());

        // 1. Создаем шаг-мысль, чтобы LLM решила вызвать инструмент
        $run->steps()->create([
            'type' => 'thought',
            'content' => 'Test retry logic',
        ]);

        // Устанавливаем calls в 0 перед тестом
        FailingToolStub::$calls = 0;

        // Выполняем шаг. Агент должен вызвать инструмент, поймать ошибку и создать шаг 'error'
        $agent->processNextStep($run);

        $lastStep = $run->steps()->latest('id')->first();
        $this->assertEquals('error', $lastStep->type);

        // 2. Выполняем следующий проход. Агент должен увидеть ошибку и попробовать еще раз (retry)
        // Добавляем retry_count и tool/args, чтобы MockLLMService знал, что повторять
        \Illuminate\Support\Facades\DB::table('steps')->where('id', $lastStep->id)->update([
            'metadata' => json_encode(array_merge($lastStep->metadata ?? [], [
                'retry_count' => 0,
                'tool' => 'failing_tool',
                'args' => []
            ]))
        ]);

        // ПЕРЕД вторым проходом убедимся, что calls=1 (после первой попытки)
        // Если processNextStep вызовет handleToolCall, calls станет 2.

        $agent->processNextStep($run);

        // После processNextStep должен был создаться CALL (через MockLLMService) и вызваться handleToolCall
        // который должен был создать OBSERVATION, так как calls теперь 2.
        $lastStep = $run->fresh()->steps()->orderBy('id', 'desc')->first();
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
