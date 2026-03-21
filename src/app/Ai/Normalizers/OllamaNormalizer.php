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

        // 2. Remove <thinking> tags if they exist
        $text = preg_replace('/<thinking>.*?<\/thinking>/s', '', $text);

        // 3. Remove thinking artifacts (some models use markdown block)
        $text = preg_replace('/```thinking.*?```/s', '', $text);

        // 2. Remove leading/trailing whitespace
        $text = trim($text);

        // 3. (Optional) Handle other Ollama specific artifacts if any are discovered

        return $text;
    }
}
