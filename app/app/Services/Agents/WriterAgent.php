<?php

namespace App\Services\Agents;

use App\Jobs\StepJob;
use App\Models\Run;

class WriterAgent extends NeuronAgent
{
    public function processNextStep(Run $run): void
    {
        $lastStep = $run->steps()->latest('id')->first();

        if (!$lastStep) {
            $this->createStep($run, 'thought', 'Я писатель. Мне нужно оформить ответ красиво.');
            StepJob::dispatch($run);
            return;
        }

        if ($lastStep->type === 'thought' && $lastStep->content === 'Я писатель. Мне нужно оформить ответ красиво.') {
            $this->createStep($run, 'answer', 'Вот красиво оформленный ответ на ваш запрос: ' . $run->prompt);
            StepJob::dispatch($run);
            return;
        }

        parent::processNextStep($run);
    }
}
