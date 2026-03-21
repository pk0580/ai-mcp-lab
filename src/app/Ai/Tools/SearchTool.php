<?php

namespace App\Ai\Tools;

use App\Ai\Attributes\Description;
use App\Models\Document;
use App\Services\EmbeddingServiceInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Pgvector\Laravel\Distance;
use Stringable;

class SearchTool implements ToolInterface
{
    public function __construct(
        private ?EmbeddingServiceInterface $embeddingService = null
    ) {
        $this->embeddingService = $embeddingService ?? app(EmbeddingServiceInterface::class);
    }
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

        if (empty($query)) {
            return "Пожалуйста, введите поисковый запрос.";
        }

        // Ограничиваем длину текста для эмбеддинга
        $embeddingQuery = mb_substr($query, 0, 8000);
        $vector = $this->embeddingService->getEmbedding($embeddingQuery);
        $dimension = count($vector->toArray());

        $column = match ($dimension) {
            768 => 'embedding_768',
            1024 => 'embedding_1024',
            1536 => 'embedding_1536',
            default => null,
        };

        if (!$column) {
            return "Ошибка: Неподдерживаемая размерность вектора ({$dimension}).";
        }

        $documents = Document::query()
            ->nearestNeighbors($column, $vector, Distance::L2)
            ->limit(5)
            ->get();

        if ($documents->isEmpty()) {
            return "Результаты поиска отсутствуют. В базе данных не найдено релевантных документов для запроса: '{$query}'.";
        }

        $results = $documents->map(function (Document $doc) {
            return "--- Источник: {$doc->source} ---\n{$doc->content}";
        })->implode("\n\n");

        return "Результаты поиска для '{$query}':\n\n{$results}";
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
