<?php

namespace App\Ai\Agents;

use App\Ai\Tools\SearchTool;
use Stringable;

class ResearcherAgent extends NeuronAgent
{
    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are a Researcher Agent. Your goal is to find accurate information using available search tools and provide a detailed summary.';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return iterable<\App\Ai\Tools\ToolInterface>
     */
    public function tools(): iterable
    {
        return [
            'search' => new SearchTool(),
        ];
    }
}
