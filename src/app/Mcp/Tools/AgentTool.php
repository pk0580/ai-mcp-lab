<?php

namespace App\Mcp\Tools;

use App\Jobs\StepJob;
use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delegate a task to another agent. Args: agent_type, prompt')]
class AgentTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $agentType = $request->get('agent_type') ?? 'researcher';
        $prompt = $request->get('prompt') ?? '';

        $run = Run::create([
            'prompt' => $prompt,
            'agent_type' => $agentType,
            'status' => 'pending',
        ]);

        StepJob::dispatch($run);

        return Response::text("Task delegated to {$agentType}. New Run ID: {$run->id}");
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'agent_type' => $schema->string('The type of agent to delegate the task to (e.g., researcher, writer).'),
            'prompt' => $schema->string('The task prompt for the agent.'),
        ];
    }
}
