<?php

namespace App\Services\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchTool implements ToolInterface
{
    public function getName(): string
    {
        return 'search';
    }

    public function description(): Stringable|string
    {
        return 'Search for information on the Internet or in the database.';
    }

    public function handle(Request $request): Stringable|string
    {
        $args = $request->all();
        $query = $args['query'] ?? '';
        return "Search result for '{$query}': Found some interesting facts about Multi-agent Systems.";

    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string('The search query.'),
        ];
    }
}
