<?php

namespace App\Ai\Tools;

use Laravel\Ai\Contracts\Tool as AiTool;

interface ToolInterface extends AiTool
{
    public function getName(): string;
}
