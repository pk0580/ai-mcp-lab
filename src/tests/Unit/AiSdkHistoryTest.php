<?php

namespace Tests\Unit;

use App\Models\Run;
use App\Models\Step;
use App\Services\LLM\AiSdkService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use ReflectionClass;

class AiSdkHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_messages_order_and_content()
    {
        $run = Run::create([
            'prompt' => 'Привет!',
            'status' => 'running',
            'agent_type' => 'neuron'
        ]);

        // Step 1: Call tool
        $callStep = Step::create([
            'run_id' => $run->id,
            'type' => 'call',
            'content' => 'Вызываю инструмент search',
            'metadata' => ['tool' => 'search', 'args' => ['query' => 'привет'], 'call_id' => '123']
        ]);

        // Step 2: Observation
        $obsStep = Step::create([
            'run_id' => $run->id,
            'type' => 'observation',
            'content' => 'Found some interesting facts about Multi-agent Systems.',
            'metadata' => ['tool' => 'search', 'args' => ['query' => 'привет']]
        ]);

        $service = new AiSdkService();
        $reflection = new ReflectionClass(AiSdkService::class);
        $method = $reflection->getMethod('buildMessages');
        $method->setAccessible(true);

        $messages = $method->invoke($service, $run);

        $this->assertCount(2, $messages);
        $this->assertInstanceOf(AssistantMessage::class, $messages[0]);
        $this->assertEquals('Вызываю инструмент search', $messages[0]->content);
        $this->assertCount(1, $messages[0]->toolCalls);
        $this->assertEquals('search', $messages[0]->toolCalls[0]->name);
        $this->assertEquals('123', $messages[0]->toolCalls[0]->id);

        $this->assertInstanceOf(ToolResultMessage::class, $messages[1]);
        $this->assertCount(1, $messages[1]->toolResults);
        $this->assertEquals('123', $messages[1]->toolResults[0]->id);
        $this->assertEquals('Found some interesting facts about Multi-agent Systems.', $messages[1]->toolResults[0]->result);
    }
}
