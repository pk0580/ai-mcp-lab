<?php

namespace App\Services;

use Pgvector\Laravel\Vector;

class EmbeddingService implements EmbeddingServiceInterface
{
    protected EmbeddingServiceInterface $implementation;

    public function __construct(?EmbeddingServiceInterface $implementation = null)
    {
        $this->implementation = $implementation ?? new MockEmbeddingService();
    }

    public function getEmbedding(string $text): Vector
    {
        return $this->implementation->getEmbedding($text);
    }
}
