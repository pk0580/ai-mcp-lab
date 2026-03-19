<?php

namespace App\Services\Agents;

use App\Jobs\StepJob;
use App\Mcp\McpRegistry;
use App\Models\AgentStep;
use App\Models\Run;
use App\Models\Step;
use App\Models\StepEmbedding;
use App\Services\EmbeddingService;
use App\Services\EmbeddingServiceInterface;
use App\Services\LLM\LLMServiceInterface;
use Illuminate\Support\Collection;
use Laravel\Mcp\Server\Tool;
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
        $this->embeddingService = $embeddingService
            ?? (app()->bound(EmbeddingServiceInterface::class)
                ? app(EmbeddingServiceInterface::class)
                : new EmbeddingService());

        $this->llmService = $llmService ?? app(LLMServiceInterface::class);

        // По умолчанию загружаем инструменты из реестра
        $mcpTools = McpRegistry::getTools()->toArray();
        foreach (array_merge($mcpTools, $tools) as $name => $tool) {
            if ($tool instanceof Tool) {
                $this->addTool(is_string($name) ? $name : (string)$name, $tool);
            }
        }
    }

    public function addTool(string $name, Tool $tool): void
    {
        // В MCP инструментах имя можно получить из атрибута, но мы используем переданное имя или ключ
        $this->tools[$name] = $tool;
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
                    $mcpRequest = new \Laravel\Mcp\Request(arguments: $toolArgs);
                    $response = $this->tools[$toolName]->handle($mcpRequest);
                    $result = (string)$response->content();
                    $this->createStep($run, 'observation', $result);
                } catch (\Exception $e) {
                    $retryCount++;
                    $this->createStep($run, 'error', $e->getMessage(), [
                        'tool' => $toolName,
                        'args' => $toolArgs,
                        'retry_count' => $retryCount
                    ]);

                    if ($retryCount >= $this->maxRetries) {
                        $run->update(['status' => 'failed']);
                        $this->log($run, "Превышено количество попыток ({$this->maxRetries}) для инструмента {$toolName}", 'error', 'max_retries_exceeded');
                        return;
                    }
                }
            } else {
                $this->createStep($run, 'error', "Инструмент {$toolName} не найден.", [
                    'tool' => $toolName,
                    'args' => $toolArgs,
                    'retry_count' => $retryCount
                ]);
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
