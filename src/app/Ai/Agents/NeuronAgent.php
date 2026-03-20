<?php

namespace App\Ai\Agents;

use App\Mcp\McpRegistry;
use App\Models\AgentStep;
use App\Models\Run;
use App\Models\Step;
use App\Models\StepEmbedding;
use App\Services\EmbeddingService;
use App\Services\LLM\LLMServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

class NeuronAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable, RemembersConversations;

    protected int $maxSteps = 10;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are a Neuron Agent, a helpful assistant that uses tools and structured output to solve complex tasks. You think before you act.';
    }

    protected array $customTools = [];

    public function __construct(array $tools = [], ...$args)
    {
        $this->customTools = $tools;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return iterable<\App\Ai\Tools\ToolInterface>
     */
    public function tools(): iterable
    {
        if (!empty($this->customTools)) {
            return $this->customTools;
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
        \App\Jobs\StepJob::dispatch($run);
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
        $tools = collect($this->tools())->toArray();

        try {
            $nextStep = $llm->generateNextStep($run, $tools);
            $latency = (int)((microtime(true) - $startTime) * 1000);

            $step = $this->createStep($run, $nextStep['type'], $nextStep['content'], $nextStep['metadata'] ?? []);
            $this->logAgentAction($run, $step, 'info', 'step_creation', "Generated next step: {$nextStep['type']}", $nextStep['metadata'] ?? [], $latency);

            if ($nextStep['type'] === 'call') {
                $this->handleToolCall($run, $nextStep['metadata']);
            } elseif ($nextStep['type'] === 'answer') {
                $run->update(['status' => 'completed']);
            } else {
                \App\Jobs\StepJob::dispatch($run);
            }
        } catch (\Exception $e) {
            $latency = (int)((microtime(true) - $startTime) * 1000);
            $step = $this->createStep($run, 'error', $e->getMessage());
            $this->logAgentAction($run, $step, 'error', 'system_error', $e->getMessage(), ['trace' => $e->getTraceAsString()], $latency);
            $run->update(['status' => 'failed']);
        }
    }

    /**
     * Handle a tool call and create an observation step.
     */
    protected function handleToolCall(Run $run, array $metadata): void
    {
        $toolName = $metadata['tool'];
        $args = $metadata['args'] ?? [];
        $availableTools = collect($this->tools());

        if ($availableTools->has($toolName)) {
            try {
                $tool = $availableTools->get($toolName);

                $reflection = new \ReflectionMethod($tool, 'handle');
                $parameters = $reflection->getParameters();
                $resolvedArgs = [];

                foreach ($parameters as $parameter) {
                    $name = $parameter->getName();
                    if (array_key_exists($name, $args)) {
                        $resolvedArgs[] = $args[$name];
                    } elseif ($parameter->isDefaultValueAvailable()) {
                        $resolvedArgs[] = $parameter->getDefaultValue();
                    } else {
                        throw new \InvalidArgumentException("Missing required argument: {$name}");
                    }
                }

                $response = $reflection->invokeArgs($tool, $resolvedArgs);

                if ($response instanceof \Laravel\Mcp\Response) {
                    $content = (string)$response->content();
                } else {
                    $content = (string)$response;
                }

                $step = $this->createStep($run, 'observation', $content, ['tool' => $toolName, 'args' => $args]);
                $this->logAgentAction($run, $step, 'info', 'step_creation', "Tool {$toolName} executed successfully", ['result' => $content]);
            } catch (\Exception $e) {
                $step = $this->createStep($run, 'error', "Tool {$toolName} failed: " . $e->getMessage(), array_merge($metadata, ['tool' => $toolName, 'args' => $args]));
                $this->logAgentAction($run, $step, 'error', 'step_creation', $e->getMessage());
            }
        } else {
            $step = $this->createStep($run, 'error', "Tool {$toolName} not found", array_merge($metadata, ['tool' => $toolName]));
            $this->logAgentAction($run, $step, 'error', 'step_creation', "Tool {$toolName} not found");
        }

        \App\Jobs\StepJob::dispatch($run);
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

        $embeddingService = resolve(EmbeddingService::class);
        $vector = $embeddingService->getEmbedding($content);

        StepEmbedding::create([
            'step_id' => $step->id,
            'embedding' => $vector
        ]);

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
        $embeddingService = resolve(EmbeddingService::class);
        $vector = $embeddingService->getEmbedding($query);

        return StepEmbedding::query()
            ->nearestNeighbors('embedding', $vector, \Pgvector\Laravel\Distance::L2)
            ->limit($limit)
            ->get()
            ->map(fn($se) => $se->step);
    }
}
