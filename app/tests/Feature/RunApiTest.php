<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Models\Step;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_run(): void
    {
        $response = $this->postJson('/api/runs', [
            'prompt' => 'Hello, agent!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('prompt', 'Hello, agent!')
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('runs', [
            'prompt' => 'Hello, agent!',
            'status' => 'pending',
        ]);
    }

    public function test_validate_prompt_on_create_run(): void
    {
        $response = $this->postJson('/api/runs', [
            'prompt' => 'Hi', // too short
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    public function test_can_get_run_details(): void
    {
        $run = Run::create([
            'prompt' => 'Test prompt',
            'status' => 'running',
        ]);

        Step::create([
            'run_id' => $run->id,
            'type' => 'thought',
            'content' => 'I am thinking',
        ]);

        $response = $this->getJson("/api/runs/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $run->id)
            ->assertJsonPath('prompt', 'Test prompt')
            ->assertJsonCount(1, 'steps')
            ->assertJsonPath('steps.0.content', 'I am thinking');
    }
}
