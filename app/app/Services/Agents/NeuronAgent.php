<?php

namespace App\Services\Agents;

use App\Models\Run;
use App\Models\Step;
use App\Services\Tools\ToolInterface;

class NeuronAgent
{
    protected array $tools = [];

    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            $this->addTool($tool);
        }
    }

    public function addTool(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function run(Run $run): void
    {
        $run->update(['status' => 'running']);

        // Базовый цикл рассуждений (заглушка для демонстрации)
        // В реальном приложении здесь был бы вызов LLM

        // Шаг 1: Рассуждение (Thought)
        $this->createStep($run, 'thought', 'Мне нужно найти информацию о мультиагентных системах, чтобы ответить на запрос.');

        // Шаг 2: Вызов инструмента (Action)
        $toolName = 'search';
        $toolArgs = ['query' => 'multiagent systems'];
        $this->createStep($run, 'call', "Использую инструмент {$toolName} с аргументами: " . json_encode($toolArgs), [
            'tool' => $toolName,
            'args' => $toolArgs
        ]);

        // Выполнение инструмента
        if (isset($this->tools[$toolName])) {
            $result = $this->tools[$toolName]->execute($toolArgs);
            $this->createStep($run, 'observation', $result);
        }

        // Шаг 3: Финальный ответ (Answer)
        $this->createStep($run, 'answer', 'Мультиагентная система — это система, состоящая из множества взаимодействующих агентов.');

        $run->update(['status' => 'completed']);
    }

    protected function createStep(Run $run, string $type, string $content, array $metadata = []): Step
    {
        return $run->steps()->create([
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }
}
