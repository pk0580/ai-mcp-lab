<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Services\Agents\NeuronAgent;
use App\Services\Tools\SearchTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NeuronAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_creates_steps_on_run(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $run = Run::create([
            'prompt' => 'What is a multi-agent system?',
            'status' => 'pending',
        ]);

        $agent = new NeuronAgent([
            new SearchTool(),
        ]);

        $agent->run($run);

        $this->assertEquals('running', $run->fresh()->status);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\StepJob::class);
    }

    public function test_agent_processes_steps_asynchronously(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $run = Run::create([
            'prompt' => 'What is a multi-agent system?',
            'status' => 'pending',
        ]);

        $agent = new NeuronAgent([
            new SearchTool(),
        ]);

        // Имитируем запуск через run
        $run->update(['status' => 'running']);

        // 1. Первый шаг: Thought
        $agent->processNextStep($run);
        $this->assertCount(1, $run->refresh()->steps);
        $this->assertEquals('thought', $run->steps->last()->type);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\StepJob::class, 1);

        // 2. Второй шаг: Call (Action)
        $agent->processNextStep($run);
        $this->assertCount(2, $run->refresh()->steps);
        $this->assertEquals('call', $run->steps->last()->type);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\StepJob::class, 2);

        // 3. Третий шаг: Observation
        $agent->processNextStep($run);
        $this->assertCount(3, $run->refresh()->steps);
        $this->assertEquals('observation', $run->steps->last()->type);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\StepJob::class, 3);

        // 4. Четвертый шаг: Answer
        $agent->processNextStep($run);
        $this->assertCount(4, $run->refresh()->steps);
        $this->assertEquals('answer', $run->steps->last()->type);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\StepJob::class, 4);

        // 5. Завершение
        $agent->processNextStep($run);
        $this->assertEquals('completed', $run->fresh()->status);
        // Больше не пушится Job
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\StepJob::class, 4);
    }
}
