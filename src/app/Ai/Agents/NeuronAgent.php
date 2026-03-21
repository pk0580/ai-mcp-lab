<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ToolInterface;
use App\Jobs\StepJob;
use App\Mcp\McpRegistry;
use App\Models\AgentStep;
use App\Models\Run;
use App\Models\Step;
use App\Models\StepEmbedding;
use App\Services\LLM\LLMServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\Request;
use Stringable;

class NeuronAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable, RemembersConversations;

    protected int $maxSteps = 50;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'Вы — Neuron Agent, полезный помощник, использующий инструменты и структурированный вывод для решения сложных задач.
        При использовании инструмента `delegate`, всегда включайте в `prompt` всю важную информацию, которую вы уже узнали,
        чтобы подчиненный агент имел полный контекст задачи. Вы думаете, прежде чем действовать.';
    }

    protected array $customTools = [];

    public function __construct(array $tools = [], ...$args)
    {
        $this->customTools = empty($tools) ? McpRegistry::getTools()->all() : $tools;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return iterable<ToolInterface>
     */
    public function tools(): iterable
    {
        if (!empty($this->customTools)) {
            // Если передан ассоциативный массив, вернем его значения
            return is_array($this->customTools) && !array_is_list($this->customTools)
                ? $this->customTools
                : $this->customTools;
        }

        return McpRegistry::getTools()->all();
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'thought' => $schema->string('Your internal reasoning process.')->required(),
            'answer' => $schema->string('The final answer or result.')->required(),
        ];
    }

    /**
     * Initialize and run the agent.
     */
    public function run(Run $run): void
    {
        $run->update(['status' => 'running']);
        StepJob::dispatch($run);
    }

    /**
     * Process the next step in the agent's reasoning loop.
     */
    public function processNextStep(Run $run): void
    {
        $status = $run->fresh()->status;
        if ($status !== 'running' && $status !== 'pending') {
            return;
        }

        if ($status === 'pending') {
            $run->update(['status' => 'running']);
        }

        $stepsCount = $run->steps()->count();
        if ($stepsCount >= $this->maxSteps) {
            $step = $this->createStep($run, 'answer', 'Превышено максимальное количество шагов (' . $this->maxSteps . ').');
            $this->logAgentAction($run, $step, 'error', 'step_limit', 'Max steps reached');
            $run->update(['status' => 'failed']);
            return;
        }

        $startTime = microtime(true);
        $llm = resolve(LLMServiceInterface::class);
        $tools = collect($this->tools())->mapWithKeys(function ($tool) {
            $name = method_exists($tool, 'name') ? $tool->name() : (method_exists($tool, 'getName') ? $tool->getName() : class_basename($tool));
            return [$name => $tool];
        })->toArray();

        try {
            // Отправляем информационный шаг о начале генерации, если это первый шаг
            if ($stepsCount === 0) {
                $this->createStep($run, 'info', 'Начинаю обработку запроса...');
            }

            $nextStep = $llm->generateNextStep($run, $tools);
            $latency = (int)((microtime(true) - $startTime) * 1000);

            $step = $this->createStep($run, $nextStep['type'], $nextStep['content'], $nextStep['metadata'] ?? []);
            // Мы перенесли базовое логирование в createStep, здесь можно добавить уточняющее логирование с задержкой
            $this->logAgentAction($run, $step, 'info', 'step_execution', "Generated next step: {$nextStep['type']}", $nextStep['metadata'] ?? [], $latency);

            if ($nextStep['type'] === 'call') {
                $this->handleToolCall($run, $nextStep['metadata']);
            } elseif ($nextStep['type'] === 'answer') {
                $run->update(['status' => 'completed']);
            } elseif ($nextStep['type'] === 'error') {
                $run->update(['status' => 'failed']);
            } else {
                \Illuminate\Support\Facades\Log::info("Dispatching next StepJob for Run #{$run->id} (type: {$nextStep['type']})");
                StepJob::dispatch($run);
            }
        } catch (\Throwable $e) {
            $latency = (int)((microtime(true) - $startTime) * 1000);
            $step = $this->createStep($run, 'error', $e->getMessage());
            $this->logAgentAction($run, $step, 'error', 'system_error', $e->getMessage(), ['trace' => $e->getTraceAsString()], $latency);
            $run->update(['status' => 'failed']);
        }
    }

    /**
     * Обработка вызова инструмента и создание шага наблюдения (observation).
     */
    protected function handleToolCall(Run $run, array $metadata): void
    {
        // Извлекаем имя инструмента и аргументы из метаданных, полученных от LLM через AI SDK
        $toolName = $metadata['tool'];
        $args = $metadata['args'] ?? [];
        $availableTools = collect($this->tools())->mapWithKeys(function ($tool) {
            $name = method_exists($tool, 'name') ? $tool->name() : (method_exists($tool, 'getName') ? $tool->getName() : class_basename($tool));
            return [$name => $tool];
        });

            if ($availableTools->has($toolName)) {
                try {
                    // Извлекаем конкретный объект инструмента
                    $tool = $availableTools->get($toolName);

                    // Создаем объект запроса для инструмента Laravel AI
                    $request = new Request($args);

                    // Отправляем информационный шаг перед выполнением инструмента
                    $this->createStep($run, 'info', "Выполняю инструмент {$toolName}...", ['args' => $args]);

                    // Выполняем логику инструмента
                    $response = $tool->handle($request);

                    if ($response instanceof \Laravel\Mcp\Response) {
                        $content = (string)$response->content();
                    } else {
                        $content = (string)$response;
                    }

                    $step = $this->createStep($run, 'observation', $content, ['tool' => $toolName, 'args' => $args]);
                    $this->logAgentAction($run, $step, 'info', 'tool_execution', "Инструмент {$toolName} успешно выполнен", ['result' => $content]);
                } catch (\Throwable $e) {
                    // В случае ошибки создаем шаг 'error', чтобы агент мог попробовать исправить ситуацию
                    $step = $this->createStep($run, 'error', "Ошибка инструмента {$toolName}: " . $e->getMessage(), array_merge($metadata, ['tool' => $toolName, 'args' => $args]));
                    $this->logAgentAction($run, $step, 'error', 'step_creation', $e->getMessage());
                }
            } else {
                // Если инструмент не найден в реестре MCP
                $step = $this->createStep($run, 'error', "Инструмент {$toolName} не найден", array_merge($metadata, ['tool' => $toolName]));
                $this->logAgentAction($run, $step, 'error', 'step_creation', "Инструмент {$toolName} не найден");
            }

            \Illuminate\Support\Facades\Log::info("Dispatching StepJob after tool call/observation for Run #{$run->id}");
            StepJob::dispatch($run);
        }

    /**
     * Create a new step and generate its embedding.
     */
    protected function createStep(Run $run, string $type, string $content, array $metadata = []): Step
    {
        $step = $run->steps()->create([
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata
        ]);

        $this->logAgentAction($run, $step, 'info', 'step_creation', "Created step: {$type}");

        try {
            $embeddingService = resolve(\App\Services\EmbeddingServiceInterface::class);
            // Ограничиваем длину текста для эмбеддинга, чтобы не превышать лимиты контекста Ollama
            $embeddingContent = mb_substr($content, 0, 8000);
            $vector = $embeddingService->getEmbedding($embeddingContent);
            $dimension = count($vector->toArray());

            $data = ['step_id' => $step->id];

            if ($dimension === 768) {
                $data['embedding_768'] = $vector;
            } elseif ($dimension === 1024) {
                $data['embedding_1024'] = $vector;
            } elseif ($dimension === 1536) {
                $data['embedding_1536'] = $vector;
            }

            StepEmbedding::create($data);
        } catch (\Throwable $e) {
            // Логируем ошибку, но не прерываем выполнение, чтобы агент мог продолжить работу без памяти для этого шага
            \Illuminate\Support\Facades\Log::warning("Не удалось создать эмбеддинг для шага {$step->id}: " . $e->getMessage());
        }

        return $step;
    }

    /**
     * Log an agent action.
     */
    protected function logAgentAction(Run $run, Step $step, string $level, string $category, string $message, array $context = [], int $latency = 0): void
    {
        AgentStep::create([
            'run_id' => $run->id,
            'step_id' => $step->id,
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => $context,
            'latency_ms' => $latency
        ]);
    }

    /**
     * Retrieve relevant information from memory (past steps).
     */
    public function retrieveFromMemory(string $query, int $limit = 5): \Illuminate\Support\Collection
    {
        try {
            $embeddingService = resolve(\App\Services\EmbeddingServiceInterface::class);
            // Ограничиваем длину текста для эмбеддинга
            $embeddingQuery = mb_substr($query, 0, 8000);
            $vector = $embeddingService->getEmbedding($embeddingQuery);
            $dimension = count($vector->toArray());

            $column = 'embedding_1536';
            if ($dimension === 768) {
                $column = 'embedding_768';
            } elseif ($dimension === 1024) {
                $column = 'embedding_1024';
            }

            return StepEmbedding::query()
                ->nearestNeighbors($column, $vector, \Pgvector\Laravel\Distance::L2)
                ->limit($limit)
                ->get()
                ->map(fn($se) => $se->step);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Не удалось выполнить поиск в памяти: " . $e->getMessage());
            return collect();
        }
    }
}
