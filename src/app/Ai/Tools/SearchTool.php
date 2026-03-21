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

    public function name(): string
    {
        return 'search';
    }

    #[Description('Search for information on the Internet or in the database.')]
    public function handle(Request $request): Stringable|string
    {
        $query = $request['query'] ?? '';
        return "Результат поиска для '{$query}': Некая полезная информация.";
    }

    public function description(): Stringable|string
    {
        return 'Поиск информации в интернете или в базе данных.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string('The search query.')->required(),
        ];
    }
}
