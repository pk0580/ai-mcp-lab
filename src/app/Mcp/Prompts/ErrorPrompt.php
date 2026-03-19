<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Промпт для обработки ошибок инструментов.')]
class ErrorPrompt extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        $toolName = $request->get('toolName');
        $retryCount = $request->get('retryCount');
        $error = $request->get('error');

        return Response::text("Ошибка при вызове {$toolName} (попытка {$retryCount}): {$error}. Пожалуйста, попробуй еще раз, если это имеет смысл.");
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('toolName', 'The name of the tool that failed.'),
            new Argument('retryCount', 'The number of retries attempted.'),
            new Argument('error', 'The error message.'),
        ];
    }
}
