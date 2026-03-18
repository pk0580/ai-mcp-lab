<?php

namespace App\Services\Agents;

use App\Jobs\StepJob;
use App\Models\Run;
use App\Services\EmbeddingServiceInterface;
use App\Services\LLM\LLMServiceInterface;
use App\Services\Tools\SearchTool;

class ResearcherAgent extends NeuronAgent
{
    public function __construct(
        array $tools = [],
        ?EmbeddingServiceInterface $embeddingService = null,
        ?LLMServiceInterface $llmService = null
    ) {
        if (empty($tools)) {
            $tools = [new SearchTool()];
        }
        parent::__construct($tools, $embeddingService, $llmService);
    }
}
