<?php

namespace App\Jobs;

use App\Models\Run;
use App\Services\Agents\NeuronAgent;
use App\Services\Tools\SearchTool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAgentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Run $run)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $agent = new NeuronAgent([
            new SearchTool(),
        ]);
        $agent->run($this->run);
    }
}
