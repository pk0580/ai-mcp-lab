<?php

namespace App\Services\LLM;

use App\Models\Run;
use App\Models\Step;
use App\Mcp\Prompts\ErrorPrompt;
use App\Mcp\Prompts\SystemPrompt;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool as McpTool;
use function Laravel\Ai\{agent};
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

class AiSdkService implements LLMServiceInterface
{
    public function generateNextStep(Run $run, array $tools): array
    {
        $instructions = (string) (new SystemPrompt())->handle(new Request())->content();

        $messages = $this->buildMessages($run);

        // Преобразуем MCP инструменты в формат, который понимает Laravel Ai
        $aiTools = array_values($tools);

        $response = agent($instructions, $messages->all(), $aiTools)
            ->prompt($run->prompt);

        if ($response->toolCalls->isNotEmpty()) {
            /** @var ToolCall $toolCall */
            $toolCall = $response->toolCalls->first();

            return [
                'type' => 'call',
                'content' => $response->text ?: "Вызываю инструмент {$toolCall->name}",
                'metadata' => [
                    'tool' => $toolCall->name,
                    'args' => $toolCall->arguments,
                    'call_id' => $toolCall->id,
                ]
            ];
        }

        return [
            'type' => 'answer',
            'content' => $response->text,
            'metadata' => []
        ];
    }

    protected function buildMessages(Run $run): Collection
    {
        $messages = new Collection();
        $messages->push(new UserMessage($run->prompt));

        $run->steps->each(function (Step $step) use ($messages) {
            switch ($step->type) {
                case 'thought':
                case 'answer':
                    $messages->push(new AssistantMessage($step->content));
                    break;
                case 'call':
                    $toolName = $step->metadata['tool'] ?? 'unknown';
                    $args = $step->metadata['args'] ?? [];
                    $callId = $step->metadata['call_id'] ?? uniqid('call_');

                    $toolCall = new ToolCall($callId, $toolName, $args);
                    $messages->push(new AssistantMessage($step->content, new Collection([$toolCall])));
                    break;
                case 'observation':
                    // Ищем предыдущий шаг 'call', чтобы связать результат
                    $lastCallStep = $step->run->steps()
                        ->where('type', 'call')
                        ->where('id', '<', $step->id)
                        ->orderBy('id', 'desc')
                        ->first();

                    $callId = $lastCallStep->metadata['call_id'] ?? 'unknown';
                    $toolName = $lastCallStep->metadata['tool'] ?? 'unknown';
                    $args = $lastCallStep->metadata['args'] ?? [];

                    $toolResult = new ToolResult($callId, $toolName, $args, $step->content);
                    $messages->push(new ToolResultMessage(new Collection([$toolResult])));
                    break;
                case 'error':
                    $toolName = $step->metadata['tool'] ?? 'unknown';
                    $args = $step->metadata['args'] ?? [];
                    $retryCount = $step->metadata['retry_count'] ?? 0;
                    $errorText = (string) (new ErrorPrompt())->handle(new Request(arguments: [
                        'toolName' => $toolName,
                        'retryCount' => $retryCount,
                        'error' => $step->content,
                    ]))->content();
                    $messages->push(new UserMessage($errorText));
                    break;
            }
        });

        return $messages;
    }
}
