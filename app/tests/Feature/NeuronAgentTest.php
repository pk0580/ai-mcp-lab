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
        $run = Run::create([
            'prompt' => 'What is a multi-agent system?',
            'status' => 'pending',
        ]);

        $agent = new NeuronAgent([
            new SearchTool(),
        ]);

        $agent->run($run);

        $this->assertEquals('completed', $run->fresh()->status);
        $this->assertCount(4, $run->steps);

        $this->assertDatabaseHas('runs', [
            'prompt' => 'What is a multi-agent system?',
            'status' => 'completed',
        ]);

        $this->assertDatabaseCount('steps', 4);
    }
}
