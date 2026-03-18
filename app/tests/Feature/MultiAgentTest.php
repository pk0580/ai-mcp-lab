<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Models\Step;
use App\Services\Agents\AgentFactory;
use App\Services\Tools\AgentTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MultiAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_researcher_agent_delegates_to_writer(): void
    {
        Queue::fake();

        // 1. Создаем запуск для исследователя
        $run = Run::create([
            'prompt' => 'Research about AI',
            'agent_type' => 'researcher',
            'status' => 'pending',
        ]);

        $agent = AgentFactory::create($run);

        // Первый шаг исследователя
        $agent->processNextStep($run);
        $this->assertEquals('Я исследователь. Мне нужно собрать факты по запросу.', $run->steps()->first()->content);

        // Второй шаг исследователя - поиск
        $agent->processNextStep($run);
        // В новой реализации NeuronAgent создание call сразу выполняет инструмент и создает observation.
        // Поэтому проверяем, что последние шаги включают call нужного инструмента.
        $steps = $run->steps()->latest('id')->get();
        $callStep = $steps->firstWhere('type', 'call');
        $this->assertNotNull($callStep);
        $this->assertEquals('search', $callStep->metadata['tool']);
        $this->assertEquals('observation', $run->steps()->latest('id')->first()->type);

        // Имитируем выполнение AgentTool (делегирование) вручную для теста
        $tool = new AgentTool();
        $result = $tool->execute(['agent_type' => 'writer', 'prompt' => 'Write about AI based on research']);

        $this->assertStringContainsString('Task delegated to writer', $result);

        // Проверяем, что создался новый Run для писателя
        $writerRun = Run::where('agent_type', 'writer')->first();
        $this->assertNotNull($writerRun);
        $this->assertEquals('Write about AI based on research', $writerRun->prompt);

        // Проверяем, что StepJob был запущен для нового Run
        Queue::assertPushed(\App\Jobs\StepJob::class, function ($job) use ($writerRun) {
            // Используем Reflection для проверки защищенного свойства $run в StepJob
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('run');
            $property->setAccessible(true);
            return $property->getValue($job)->id === $writerRun->id;
        });
    }

    public function test_writer_agent_processes_independently(): void
    {
        Queue::fake();

        $run = Run::create([
            'prompt' => 'Write a story',
            'agent_type' => 'writer',
            'status' => 'pending',
        ]);

        $agent = AgentFactory::create($run);

        // Первый шаг писателя
        $agent->processNextStep($run);
        $this->assertEquals('Я писатель. Мне нужно оформить ответ красиво.', $run->steps()->first()->content);

        // Второй шаг писателя - ответ
        $agent->processNextStep($run);
        $this->assertEquals('answer', $run->steps()->latest('id')->first()->type);
        $this->assertStringContainsString('Вот красиво оформленный ответ', $run->steps()->latest('id')->first()->content);
    }
}
