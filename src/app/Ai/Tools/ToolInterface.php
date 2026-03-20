<?php

namespace App\Ai\Tools;

interface ToolInterface
{
    public function getName(): string;
    public function description(): \Stringable|string;
    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array;
}
