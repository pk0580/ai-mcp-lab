<?php

namespace App\Services\Tools;

interface ToolInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $args): string;
}
