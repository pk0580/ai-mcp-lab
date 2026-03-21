<?php

namespace App\Ai\Normalizers;

class OllamaNormalizer implements NormalizerInterface
{
    public function normalize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // 1. Remove <thought> tags if they exist (sometimes models output thoughts in tags)
        $text = preg_replace('/<thought>.*?<\/thought>/s', '', $text);

        // 2. Remove leading/trailing whitespace
        $text = trim($text);

        // 3. (Optional) Handle other Ollama specific artifacts if any are discovered

        return $text;
    }
}
