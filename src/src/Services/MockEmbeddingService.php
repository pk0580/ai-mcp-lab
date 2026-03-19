<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Pgvector\Laravel\Vector;

class MockEmbeddingService implements EmbeddingServiceInterface
{
    /**
     * Генерирует вектор для текста.
     * В данной реализации мы используем имитацию (случайный вектор),
     * так как нет доступа к реальному API (OpenAI/Ollama).
     */
    public function getEmbedding(string $text): Vector
    {
        if (empty(trim($text))) {
            return new Vector(array_fill(0, 1536, 0.0));
        }

        $cacheKey = 'embedding_' . md5($text);

        return Cache::remember($cacheKey, now()->addDays(1), function () use ($text) {
            // Для детерминированности в тестах можно использовать хэш,
            // но для простоты шага сделаем псевдослучайный вектор на основе текста.

            $seed = crc32($text);
            mt_srand($seed);

            $embedding = [];
            for ($i = 0; $i < 1536; $i++) {
                $embedding[] = mt_rand() / mt_getrandmax() * 2 - 1;
            }

            mt_srand(); // Сброс сида

            return new Vector($embedding);
        });
    }
}
