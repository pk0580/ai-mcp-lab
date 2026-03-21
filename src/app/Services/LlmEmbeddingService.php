<?php

namespace App\Services;

use Pgvector\Laravel\Vector;
use Laravel\Ai\Embeddings;

class LlmEmbeddingService implements EmbeddingServiceInterface
{
    /**
     * Генерирует вектор для текста используя Laravel AI SDK.
     */
    public function getEmbedding(string $text, ?string $provider = null, ?string $model = null): Vector
    {
        $response = Embeddings::for([$text])->generate($provider, $model);

        return new Vector($response->first());
    }
}
