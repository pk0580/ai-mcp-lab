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
Если тебе нужно получить больше информации, вызови соответствующий инструмент.
Если у тебя достаточно информации, дай финальный ответ.
Твои мысли записывай в поле content при вызове инструмента или финальном ответе.
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
