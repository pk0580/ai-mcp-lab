<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStep extends Model
{
    protected $fillable = [
        'run_id',
        'step_id',
        'level',
        'category',
        'message',
        'context',
        'latency_ms'
    ];

    protected $casts = [
        'context' => 'array',
        'latency_ms' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(Step::class);
    }
}
