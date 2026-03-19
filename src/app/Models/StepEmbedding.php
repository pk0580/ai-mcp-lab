<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\Vector;
use Pgvector\Laravel\HasNeighbors;

class StepEmbedding extends Model
{
    use HasNeighbors;

    protected $fillable = ['step_id', 'embedding'];

    protected $casts = [
        'embedding' => Vector::class,
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(Step::class);
    }
}
