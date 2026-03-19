<?php

namespace App\Services\Tools;

use App\Jobs\StepJob;
use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class AgentTool implements ToolInterface
{
    public function getName(): string
    {
        return 'delegate';
    }

    public function description(): Stringable|string
    {
        return 'Delegate a task to another agent. Args: agent_type, prompt';
    }

    public function handle(Request $request): Stringable|string
    {
        $args = $request->all();
        $agentType = $args['agent_type'] ?? 'researcher';
        $prompt = $args['prompt'] ?? '';

        $run = Run::create([
            'prompt' => $prompt,
            'agent_type' => $agentType,
            'status' => 'pending',
        ]);

        // Мы не вызываем run() здесь напрямую, так как это может создать бесконечную рекурсию в одном процессе.
        // Вместо этого мы создаем Run, и он будет обработан асинхронно (если это настроено).
        // В рамках симуляции мы можем просто вернуть ID нового запуска.

        // Для симуляции взаимодействия мы можем запустить его через StepJob
        StepJob::dispatch($run);

        return "Task delegated to {$agentType}. New Run ID: {$run->id}";

    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'agent_type' => $schema->string('The type of agent to delegate the task to (e.g., researcher, writer).'),
            'prompt' => $schema->string('The task prompt for the agent.'),
        ];
    }
}
