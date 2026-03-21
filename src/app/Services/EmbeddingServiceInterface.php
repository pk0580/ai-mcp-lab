<?php

namespace App\Services;

use Pgvector\Laravel\Vector;

interface EmbeddingServiceInterface
{
    /**
     * Генерирует вектор для текста.
     */
    public function getEmbedding(string $text, ?string $provider = null, ?string $model = null): Vector;
}
