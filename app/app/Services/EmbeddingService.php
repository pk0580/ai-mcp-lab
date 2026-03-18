<?php

namespace App\Services;

use Pgvector\Laravel\Vector;

class EmbeddingService
{
    /**
     * Генерирует вектор для текста.
     * В данной реализации мы используем имитацию (случайный вектор),
     * так как нет доступа к реальному API (OpenAI/Ollama).
     */
    public function getEmbedding(string $text): Vector
    {
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
    }
}
