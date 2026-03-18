<?php

namespace App\Services\Tools;

class SearchTool implements ToolInterface
{
    public function getName(): string
    {
        return 'search';
    }

    public function getDescription(): string
    {
        return 'Search for information on the Internet or in the database.';
    }

    public function execute(array $args): string
    {
        $query = $args['query'] ?? '';
        return "Search result for '{$query}': Found some interesting facts about Multi-agent Systems.";
    }
}
