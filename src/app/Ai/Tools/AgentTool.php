<?php

namespace App\Ai\Tools;

use App\Ai\Attributes\Description;
use App\Jobs\StepJob;
use App\Models\Run;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Stringable;

class AgentTool implements ToolInterface
{
    public function getName(): string
    {
        return 'delegate';
    }

    #[Description('Delegate a task to another agent.')]
    public function handle(string $agent_type, string $prompt): Stringable|string
    {
        $run = Run::create([
            'prompt' => $prompt,
            'agent_type' => $agent_type,
            'status' => 'pending',
        ]);

        // Мы не вызываем run() здесь напрямую, так как это может создать бесконечную рекурсию в одном процессе.
        // Вместо этого мы создаем Run, и он будет обработан асинхронно (если это настроено).
        // В рамках симуляции мы можем просто вернуть ID нового запуска.

        // Для симуляции взаимодействия мы можем запустить его через StepJob
        StepJob::dispatch($run);

        return "Task delegated to {$agent_type}. New Run ID: {$run->id}";
    }

    public function description(): Stringable|string
    {
        return 'Delegate a task to another agent.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'agent_type' => $schema->string('The type of agent to delegate the task to (e.g., researcher, writer).')->required(),
            'prompt' => $schema->string('The task prompt for the agent.')->required(),
        ];
    }
}
