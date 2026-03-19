<?php

namespace App\Services\Agents;

use App\Models\Run;
use Illuminate\Support\Collection;

interface AgentInterface
{
    public function run(Run $run): void;
    public function processNextStep(Run $run): void;
    public function retrieveFromMemory(string $query, int $limit = 3): Collection;
}
