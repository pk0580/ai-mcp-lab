<?php

namespace App\Services\LLM;

use App\Services\Tools\ToolInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AiToolAdapter implements Tool
{
    public function __construct(protected ToolInterface $tool)
    {
    }

    public function description(): Stringable|string
    {
        return $this->tool->getDescription();
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->tool->execute($request->all());
    }

    public function schema(JsonSchema $schema): array
    {
        // Для простоты возвращаем пустую схему, так как существующие инструменты
        // не предоставляют структурированное описание параметров.
        // В реальном проекте здесь стоило бы добавить извлечение схемы из ToolInterface.
        return [];
    }

    public function getName(): string
    {
        return $this->tool->getName();
    }
}
