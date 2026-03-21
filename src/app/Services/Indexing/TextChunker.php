<?php

namespace App\Services\Indexing;

class TextChunker
{
    /**
     * Разделяет текст на чанки по абзацам с перекрытием.
     *
     * @param string $text Текст для разбиения.
     * @param int $overlap Количество абзацев для перекрытия.
     * @return array<string>
     */
    public function chunk(string $text, int $overlap = 1, int $chunkSize = 10): array
    {
        // Разделяем по переносам строк (один или более)
        $paragraphs = preg_split('/\n+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($paragraphs)) {
            return [];
        }

        $chunks = [];
        $totalParagraphs = count($paragraphs);

        // Чанк = $chunkSize абзацев, шаг = $chunkSize - $overlap.
        $step = $chunkSize - $overlap;
        if ($step <= 0) $step = 1;

        for ($i = 0; $i < $totalParagraphs; $i += $step) {
            $slice = array_slice($paragraphs, $i, $chunkSize);
            $chunks[] = implode("\n\n", $slice);

            if ($i + $chunkSize >= $totalParagraphs) {
                break;
            }
        }

        return $chunks;
    }
}
