<?php

namespace App\Services\Agents;

use App\Services\EmbeddingServiceInterface;
use App\Services\LLM\LLMServiceInterface;
use App\Mcp\Tools\SearchTool;

class ResearcherAgent extends NeuronAgent
{
    public function __construct(
        array $tools = [],
        ?EmbeddingServiceInterface $embeddingService = null,
        ?LLMServiceInterface $llmService = null
    ) {
        if (empty($tools)) {
            $tools = ['search' => new SearchTool()];
        }
        parent::__construct($tools, $embeddingService, $llmService);
    }
}
