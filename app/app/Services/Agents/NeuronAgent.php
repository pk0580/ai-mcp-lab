<?php

namespace App\Services\Agents;

use App\Jobs\StepJob;
use App\Models\Run;
use App\Models\Step;
use App\Models\StepEmbedding;
use App\Services\EmbeddingService;
use App\Services\Tools\ToolInterface;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;

class NeuronAgent implements AgentInterface
{
    protected array $tools = [];
    protected EmbeddingService $embeddingService;
    protected int $maxRetries = 3;

    public function __construct(array $tools = [], ?EmbeddingService $embeddingService = null)
    {
        $this->embeddingService = $embeddingService ?? new EmbeddingService();
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
        StepJob::dispatch($run);
    }

    public function processNextStep(Run $run): void
    {
        // В реальной системе здесь бы вызывалась LLM для принятия решения на основе истории шагов.
        // Сейчас мы имитируем переходы между шагами.

        $lastStep = $run->steps()->latest('id')->first();

        if (!$lastStep) {
            // Начальный шаг
            $this->createStep($run, 'thought', 'Мне нужно найти информацию о мультиагентных системах, чтобы ответить на запрос.');
            StepJob::dispatch($run);
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
                StepJob::dispatch($run);
                break;

            case 'call':
                $toolName = $lastStep->metadata['tool'] ?? null;
                $toolArgs = $lastStep->metadata['args'] ?? [];
                $retryCount = $lastStep->metadata['retry_count'] ?? 0;

                if ($toolName && isset($this->tools[$toolName])) {
                    try {
                        $result = $this->tools[$toolName]->execute($toolArgs);
                        $this->createStep($run, 'observation', $result);
                    } catch (\Exception $e) {
                        $this->createStep($run, 'error', $e->getMessage(), [
                            'tool' => $toolName,
                            'args' => $toolArgs,
                            'retry_count' => $retryCount
                        ]);
                    }
                } else {
                    $this->createStep($run, 'error', "Инструмент {$toolName} не найден.");
                }
                StepJob::dispatch($run);
                break;

            case 'error':
                $toolName = $lastStep->metadata['tool'] ?? null;
                $toolArgs = $lastStep->metadata['args'] ?? [];
                $retryCount = $lastStep->metadata['retry_count'] ?? 0;

                if ($retryCount < $this->maxRetries) {
                    $this->createStep($run, 'call', "Повторная попытка ({$retryCount}) для {$toolName}", [
                        'tool' => $toolName,
                        'args' => $toolArgs,
                        'retry_count' => $retryCount + 1
                    ]);
                } else {
                    $this->createStep($run, 'answer', "Ошибка после {$retryCount} попыток: " . $lastStep->content);
                }
                StepJob::dispatch($run);
                break;

            case 'observation':
                $this->createStep($run, 'reflection', 'Анализирую полученные данные: ' . $lastStep->content);
                StepJob::dispatch($run);
                break;

            case 'reflection':
                $this->createStep($run, 'answer', 'Мультиагентная система — это система, состоящая из множества взаимодействующих агентов.');
                StepJob::dispatch($run);
                break;

            case 'answer':
            default:
                $run->update(['status' => 'completed']);
                break;
        }
    }

    protected function createStep(Run $run, string $type, string $content, array $metadata = []): Step
    {
        $step = $run->steps()->create([
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
        ]);

        // Создаем эмбеддинг для шага для долговременной памяти
        $embedding = $this->embeddingService->getEmbedding($content);
        $step->embedding()->create([
            'embedding' => $embedding,
        ]);

        return $step;
    }

    /**
     * Поиск похожих шагов в памяти.
     */
    public function retrieveFromMemory(string $query, int $limit = 3): Collection
    {
        $vector = $this->embeddingService->getEmbedding($query);

        return StepEmbedding::query()
            ->nearestNeighbors('embedding', $vector, Distance::L2)
            ->limit($limit)
            ->get()
            ->map(fn($e) => $e->step);
    }
}
