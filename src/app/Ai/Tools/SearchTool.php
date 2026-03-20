<?php

namespace App\Ai\Tools;

use App\Ai\Attributes\Description;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchTool implements ToolInterface
{
    public function getName(): string
    {
        return 'search';
    }

    #[Description('Search for information on the Internet or in the database.')]
    public function handle(Request $request): Stringable|string
    {
        $query = $request->get('query');
        return "Search result for '{$query}': Found some interesting facts about Multi-agent Systems.";
    }

    public function description(): Stringable|string
    {
        return 'Search for information on the Internet or in the database.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string('The search query.')->required(),
        ];
    }
}
