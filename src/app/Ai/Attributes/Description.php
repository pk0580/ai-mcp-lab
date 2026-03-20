<?php

namespace App\Ai\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Description
{
    public function __construct(public string $description)
    {
    }
}
