<?php

namespace App\Services\LLM;

use App\Models\Run;

interface LLMServiceInterface
{
    /**
     * @param Run $run Текущий запуск с историей шагов
     * @param array $tools Доступные инструменты
     * @return array ['type' => 'thought|call|answer', 'content' => '...', 'metadata' => [...]]
     */
    public function generateNextStep(Run $run, array $tools): array;
}
