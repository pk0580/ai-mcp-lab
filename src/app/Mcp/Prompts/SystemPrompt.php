<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Системный промпт для NeuronAgent.')]
class SystemPrompt extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        return Response::text(<<<PROMPT
Ты - NeuronAgent, умный ассистент, способный использовать инструменты.
Тебе дан промпт пользователя и история твоих действий.

ИНСТРУКЦИИ ПО ПОВЕДЕНИЮ:
1. Если тебе нужно получить больше информации, вызови соответствующий инструмент.
2. НИКОГДА не говори, что тебе "не удалось найти информацию", ДО того как ты впервые вызовешь поисковый инструмент.
3. Если инструмент (например, поиск) вернул сообщение о том, что результатов нет, ОБЯЗАТЕЛЬНО сообщи об этом пользователю в своем ответе.
4. Если у тебя достаточно информации, дай финальный ответ.
5. Твои мысли записывай в поле content при вызове инструмента или финальном ответе. Будь лаконичен в описании причин вызова инструмента.
6. Пиши на том языке, на котором обратился пользователь.
PROMPT
        );
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            //
        ];
    }
}
