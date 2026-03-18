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
        \App\Jobs\StepJob::dispatch($run);
    }

    public function processNextStep(Run $run): void
    {
        // В реальной системе здесь бы вызывалась LLM для принятия решения на основе истории шагов.
        // Сейчас мы имитируем переходы между шагами.

        $lastStep = $run->steps()->latest('id')->first();

        if (!$lastStep) {
            // Начальный шаг
            $this->createStep($run, 'thought', 'Мне нужно найти информацию о мультиагентных системах, чтобы ответить на запрос.');
            \App\Jobs\StepJob::dispatch($run);
            return;
        }

        switch ($lastStep->type) {
            case 'thought':
                $toolName = 'search';
                $toolArgs = ['query' => 'multiagent systems'];
                $this->createStep($run, 'call', "Использую инструмент {$toolName} с аргументами: " . json_encode($toolArgs), [
                    'tool' => $toolName,
                    'args' => $toolArgs
                ]);
                \App\Jobs\StepJob::dispatch($run);
                break;

            case 'call':
                $toolName = $lastStep->metadata['tool'] ?? null;
                $toolArgs = $lastStep->metadata['args'] ?? [];

                if ($toolName && isset($this->tools[$toolName])) {
                    $result = $this->tools[$toolName]->execute($toolArgs);
                    $this->createStep($run, 'observation', $result);
                } else {
                    $this->createStep($run, 'error', "Инструмент {$toolName} не найден.");
                }
                \App\Jobs\StepJob::dispatch($run);
                break;

            case 'observation':
                $this->createStep($run, 'answer', 'Мультиагентная система — это система, состоящая из множества взаимодействующих агентов.');
                \App\Jobs\StepJob::dispatch($run);
                break;

            case 'answer':
            default:
                $run->update(['status' => 'completed']);
                break;
        }
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
