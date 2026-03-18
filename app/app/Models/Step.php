<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Step extends Model
{
    protected $fillable = ['run_id', 'type', 'content', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(StepEmbedding::class);
    }
}
