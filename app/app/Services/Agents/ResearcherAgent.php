<?php

namespace App\Services\Agents;

use App\Jobs\StepJob;
use App\Models\Run;
use App\Services\EmbeddingService;
use App\Services\Tools\SearchTool;

class ResearcherAgent extends NeuronAgent
{
    public function __construct(array $tools = [], ?EmbeddingService $embeddingService = null)
    {
        if (empty($tools)) {
            $tools = [new SearchTool()];
        }
        parent::__construct($tools, $embeddingService);
    }

    public function processNextStep(Run $run): void
    {
        $lastStep = $run->steps()->latest('id')->first();

        if (!$lastStep) {
            $this->createStep($run, 'thought', 'Я исследователь. Мне нужно собрать факты по запросу.');
            StepJob::dispatch($run);
            return;
        }

        if ($lastStep->type === 'thought' && $lastStep->content === 'Я исследователь. Мне нужно собрать факты по запросу.') {
            $toolName = 'search';
            $toolArgs = ['query' => $run->prompt];
            $this->createStep($run, 'call', "Ищу информацию: " . $run->prompt, [
                'tool' => $toolName,
                'args' => $toolArgs
            ]);
            StepJob::dispatch($run);
            return;
        }

        parent::processNextStep($run);
    }
}
