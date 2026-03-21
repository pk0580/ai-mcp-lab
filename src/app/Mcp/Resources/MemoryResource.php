<?php

namespace App\Mcp\Resources;

use App\Models\StepEmbedding;
use App\Services\EmbeddingServiceInterface;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Resource;
use Pgvector\Laravel\Distance;

#[Description('Provides access to the agent long-term memory via vector search. Use query parameter.')]
class MemoryResource extends Resource
{
    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        $query = $request->get('query') ?? '';

        if (empty($query)) {
            return Response::text('Please provide a query for memory retrieval.');
        }

        $embeddingService = app(EmbeddingServiceInterface::class);
        $vector = $embeddingService->getEmbedding($query);
        $dimension = count($vector->toArray());

        $column = 'embedding_1536';
        if ($dimension === 768) {
            $column = 'embedding_768';
        } elseif ($dimension === 1024) {
            $column = 'embedding_1024';
        }

        $results = StepEmbedding::query()
            ->nearestNeighbors($column, $vector, Distance::L2)
            ->limit(3)
            ->get();

        if ($results->isEmpty()) {
            return Response::text("No memories found for: '{$query}'");
        }

        $content = "Found memories for '{$query}':\n";
        foreach ($results as $result) {
            $content .= "- " . $result->step->content . "\n";
        }

        return Response::text($content);
    }
}
