<?php

namespace App\Services\Agents;

use App\Jobs\StepJob;
use App\Models\AgentStep;
use App\Models\Run;
use App\Models\Step;
use App\Models\StepEmbedding;
use App\Services\EmbeddingService;
use App\Services\EmbeddingServiceInterface;
use App\Services\LLM\LLMServiceInterface;
use App\Services\LLM\MockLLMService;
use App\Services\Tools\ToolInterface;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;

class NeuronAgent implements AgentInterface
{
    protected array $tools = [];
    protected EmbeddingServiceInterface $embeddingService;
    protected LLMServiceInterface $llmService;
    protected int $maxRetries = 3;
    protected int $maxSteps = 20;
    protected float $stepStartTime;

    public function __construct(
        array $tools = [],
        ?EmbeddingServiceInterface $embeddingService = null,
        ?LLMServiceInterface $llmService = null
    ) {
        $this->embeddingService = $embeddingService ?? new EmbeddingService();
        $this->llmService = $llmService ?? new MockLLMService();
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
        $this->stepStartTime = microtime(true);

        if ($run->steps()->count() >= $this->maxSteps) {
            $this->createStep($run, 'error', "Превышено максимальное количество шагов ({$this->maxSteps}). Остановка для предотвращения зацикливания.");
            $run->update(['status' => 'failed']);
            return;
        }

        // Получаем решение от LLM
        $decision = $this->llmService->generateNextStep($run, $this->tools);

        if ($decision['type'] === 'call') {
            $toolName = $decision['metadata']['tool'] ?? null;
            $toolArgs = $decision['metadata']['args'] ?? [];
            $retryCount = $decision['metadata']['retry_count'] ?? 0;

            $this->createStep($run, 'call', $decision['content'], $decision['metadata']);

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
            return;
        }

        $this->createStep($run, $decision['type'], $decision['content'], $decision['metadata'] ?? []);

        if ($decision['type'] === 'answer') {
            $run->update(['status' => 'completed']);
        } else {
            StepJob::dispatch($run);
        }
    }

    protected function createStep(Run $run, string $type, string $content, array $metadata = []): Step
    {
        $latency = isset($this->stepStartTime) ? (int)((microtime(true) - $this->stepStartTime) * 1000) : null;

        $step = $run->steps()->create([
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
        ]);

        $this->log($run, "Created step of type: {$type}", 'info', 'step_creation', [
            'step_id' => $step->id,
            'type' => $type
        ], $step, $latency);

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
        $startTime = microtime(true);
        $vector = $this->embeddingService->getEmbedding($query);

        $results = StepEmbedding::query()
            ->nearestNeighbors('embedding', $vector, Distance::L2)
            ->limit($limit)
            ->get();

        $latency = (int)((microtime(true) - $startTime) * 1000);

        // Находим run_id из контекста, если это возможно.
        // В данном методе у нас нет $run, поэтому логирование здесь может быть ограничено
        // или требовать передачи $run. Для упрощения пока логируем без привязки к Run если его нет.

        return $results->map(fn($e) => $e->step);
    }

    protected function log(Run $run, string $message, string $level = 'info', string $category = null, array $context = [], ?Step $step = null, ?int $latency = null): void
    {
        AgentStep::create([
            'run_id' => $run->id,
            'step_id' => $step?->id,
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => $context,
            'latency_ms' => $latency,
        ]);
    }
}
