<?php

namespace App\Ai\Tools;

use App\Ai\Attributes\Description;
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

    #[Description('Делегирует задачу другому специализированному агенту.
    Используйте этот инструмент, когда задача требует специфических навыков (например, глубокого исследования или написания кода),
    которыми обладает другой агент. Инструмент дождется выполнения задачи и вернет результат.')]
    public function handle(Request $request): Stringable|string
    {
        $agent_type = $request->get('agent_type');
        $prompt = $request->get('prompt');
        // 1. Создаем новый процесс для другого агента
        $run = Run::create([
            'prompt' => $prompt,
            'agent_type' => $agent_type,
            'status' => 'pending',
        ]);

        // 2. Запускаем выполнение
        StepJob::dispatch($run);

        // 3. Синхронное ожидание результата (MCP::call стиль)
        // В реальном продакшене лучше использовать WebSockets или Callback,
        // но для учебного примера реализуем опрос:
        if (app()->environment('testing')) {
            return "Task delegated to {$agent_type} (Run ID: {$run->id}). Check status later.";
        }

        $attempts = 0;
        while ($attempts < 30) { // Ждем до 30 секунд
            sleep(1);
            $run->refresh();

            if ($run->status === 'completed') {
                $lastStep = $run->steps()->where('type', 'answer')->latest()->first();
                return "Результат от {$agent_type}: " . ($lastStep?->content ?? 'Задача выполнена успешно.');
            }

            if ($run->status === 'failed') {
                return "Ошибка: Агент {$agent_type} не смог выполнить задачу.";
            }

            $attempts++;
        }

        return "Задача делегирована (Run ID: {$run->id}), но ответ не был получен вовремя. Продолжайте работу, проверив статус позже.";
    }

    public function description(): Stringable|string
    {
        return 'Делегирование задачи другому агенту. Позволяет передать контекст и получить готовый ответ.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'agent_type' => $schema->string('Тип агента: "researcher" для поиска данных, "writer" для обработки текста.')->required(),
            'prompt' => $schema->string('Четкая инструкция для подчиненного агента.')->required(),
        ];
    }
}
