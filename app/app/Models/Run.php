<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Run extends Model
{
    protected $fillable = ['prompt', 'status'];

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class);
    }
}
