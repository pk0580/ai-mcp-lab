<?php

namespace App\Ai\Normalizers;

class NormalizerFactory
{
    /**
     * Get normalizer for a given provider.
     *
     * @param string|null $provider
     * @return NormalizerInterface
     */
    public static function make(?string $provider = null): NormalizerInterface
    {
        $provider = $provider ?? config('ai.default');

        return match ($provider) {
            'ollama' => new OllamaNormalizer(),
            default => new DefaultNormalizer(),
        };
    }
}
