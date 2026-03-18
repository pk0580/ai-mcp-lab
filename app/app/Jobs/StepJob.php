<?php

namespace App\Jobs;

use App\Models\Run;
use App\Services\Agents\AgentFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StepJob implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Run $run)
    {
        //
    }

    public function handle(): void
    {
        $agent = AgentFactory::create($this->run);

        $agent->processNextStep($this->run);
    }
}
