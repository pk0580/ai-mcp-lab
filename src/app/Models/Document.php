<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class Document extends Model
{
    use HasNeighbors;

    protected $fillable = [
        'content',
        'embedding_1536',
        'embedding_768',
        'embedding_1024',
        'source',
        'chunk_index',
    ];

    protected $casts = [
        'embedding_1536' => Vector::class,
        'embedding_768' => Vector::class,
        'embedding_1024' => Vector::class,
    ];
}
