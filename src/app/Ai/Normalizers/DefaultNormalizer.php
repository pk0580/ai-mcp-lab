<?php

namespace App\Ai\Normalizers;

class DefaultNormalizer implements NormalizerInterface
{
    public function normalize(?string $text): ?string
    {
        return $text !== null ? trim($text) : null;
    }
}
