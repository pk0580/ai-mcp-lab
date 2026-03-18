<?php

namespace App\Services\Tools;

use App\Jobs\StepJob;
use App\Models\Run;

class AgentTool implements ToolInterface
{
    public function getName(): string
    {
        return 'delegate';
    }

    public function getDescription(): string
    {
        return 'Delegate a task to another agent. Args: agent_type, prompt';
    }

    public function execute(array $args): string
    {
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
}
