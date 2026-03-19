<?php

namespace App\Services\LLM;

use App\Models\Run;

class MockLLMService implements LLMServiceInterface
{
    public function generateNextStep(Run $run, array $tools): array
    {
        // 1. Формируем системный промпт с описанием инструментов
        $systemPrompt = $this->buildSystemPrompt($tools);

        // 2. Формируем историю сообщений из предыдущих шагов (run->steps)
        $messages = $this->buildMessages($run);

        // 3. Имитируем отправку $systemPrompt и $messages в API чата (например, GPT-4)
        // В реальном сервисе здесь будет вызов OpenAI API с Function Calling.
        // Для мока мы используем логику на основе последнего шага,
        // но она имитирует ответ от "интеллектуальной" модели, которая видит весь контекст.

        $steps = $run->steps()->orderBy('id', 'asc')->get();
        $lastStep = $steps->last();

        if (!$lastStep) {
            $content = "Анализирую запрос пользователя: \"{$run->prompt}\". Начну с поиска информации.";
            if ($run->agent_type === 'researcher') {
                $content = 'Я исследователь. Мне нужно собрать факты по запросу.';
            } elseif ($run->agent_type === 'writer') {
                $content = 'Я писатель. Мне нужно оформить ответ красиво.';
            }

            return [
                'type' => 'thought',
                'content' => $content,
                'metadata' => ['system_prompt_used' => true]
            ];
        }

        switch ($lastStep->type) {
            case 'thought':
                if ($lastStep->content === 'Я писатель. Мне нужно оформить ответ красиво.') {
                    return [
                        'type' => 'answer',
                        'content' => 'Вот красиво оформленный ответ на ваш запрос: ' . $run->prompt,
                        'metadata' => []
                    ];
                }

                if ($run->prompt === 'Loop test') {
                    return [
                        'type' => 'call',
                        'content' => 'Использую инструмент search для теста.',
                        'metadata' => [
                            'tool' => 'search',
                            'args' => ['query' => $run->prompt]
                        ]
                    ];
                }
                if ($run->prompt === 'Test retry logic') {
                    return [
                        'type' => 'call',
                        'content' => 'Calling failing tool',
                        'metadata' => [
                            'tool' => 'failing_tool',
                            'args' => []
                        ]
                    ];
                }
                if (isset($tools['search'])) {
                    return [
                        'type' => 'call',
                        'content' => 'Использую инструмент search для поиска информации.',
                        'metadata' => [
                            'tool' => 'search',
                            'args' => ['query' => $run->prompt]
                        ]
                    ];
                }
                return [
                    'type' => 'answer',
                    'content' => 'К сожалению, у меня нет инструментов для выполнения этого запроса.',
                    'metadata' => []
                ];

            case 'observation':
                if ($run->prompt === 'Loop test' || $run->prompt === 'Test retry logic') {
                    return [
                        'type' => 'thought',
                        'content' => 'Keep thinking for tests',
                        'metadata' => []
                    ];
                }
                return [
                    'type' => 'reflection',
                    'content' => "Анализирую полученные данные: {$lastStep->content}. Это позволяет мне сформулировать ответ.",
                    'metadata' => []
                ];

            case 'reflection':
                return [
                    'type' => 'answer',
                    'content' => "На основе проведенного исследования: {$lastStep->content}. (Это имитация ответа ИИ)",
                    'metadata' => []
                ];

            case 'error':
                $retryCount = $lastStep->metadata['retry_count'] ?? 0;
                if ($retryCount < 3) {
                    return [
                        'type' => 'call',
                        'content' => "Произошла ошибка, пробую еще раз. Попытка #" . ($retryCount + 1),
                        'metadata' => [
                            'tool' => $lastStep->metadata['tool'],
                            'args' => $lastStep->metadata['args'],
                            'retry_count' => $retryCount + 1
                        ]
                    ];
                }
                return [
                    'type' => 'answer',
                    'content' => "К сожалению, после нескольких попыток произошла ошибка: {$lastStep->content}",
                    'metadata' => []
                ];

            default:
                return [
                    'type' => 'answer',
                    'content' => "Задача выполнена. Итоговый результат сформирован.",
                    'metadata' => []
                ];
        }
    }

    protected function buildSystemPrompt(array $tools): string
    {
        $prompt = "Вы — полезный ИИ-агент. Вы работаете в цикле Thought -> Call -> Observation -> Reflection -> Answer.\n";
        $prompt .= "Вам доступны следующие инструменты:\n";

        foreach ($tools as $name => $tool) {
            $description = '';
            if ($tool instanceof \Laravel\Mcp\Server\Tool) {
                $reflection = new \ReflectionClass($tool);
                $attributes = $reflection->getAttributes(\Laravel\Mcp\Server\Attributes\Description::class);
                if (!empty($attributes)) {
                    $description = $attributes[0]->newInstance()->value;
                }
                $prompt .= "- {$name}: {$description}\n";
            } else {
                $prompt .= "- {$name}\n";
            }
        }

        $prompt .= "\nИспользуйте Function Calling для вызова инструментов.";
        return $prompt;
    }

    protected function buildMessages(Run $run): array
    {
        $messages = [
            ['role' => 'user', 'content' => $run->prompt]
        ];

        foreach ($run->steps()->orderBy('id')->get() as $step) {
            $role = match ($step->type) {
                'thought', 'reflection', 'answer' => 'assistant',
                'call' => 'assistant', // В OpenAI это обычно assistant message с function_call
                'observation' => 'tool', // Или 'function' в старых API
                'error' => 'system', // Или логика обработки ошибки
                default => 'assistant'
            };

            $messages[] = [
                'role' => $role,
                'content' => $step->content,
                'metadata' => $step->metadata
            ];
        }

        return $messages;
    }
}
