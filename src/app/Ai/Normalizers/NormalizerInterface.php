<?php

namespace App\Ai\Normalizers;

interface NormalizerInterface
{
    /**
     * Normalize the LLM response text.
     *
     * @param string|null $text
     * @return string|null
     */
    public function normalize(?string $text): ?string;
}
