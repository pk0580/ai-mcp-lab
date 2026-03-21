<?php

namespace App\Services\Indexing;

use App\Services\EmbeddingServiceInterface;
use App\Models\Document;

readonly class DocumentIndexer
{
    public function __construct(
        private DocumentLoader            $loader,
        private TextChunker               $chunker,
        private EmbeddingServiceInterface $embedding
    ) {}

    public function index(string $path, ?string $provider = null, ?string $model = null): void
    {
        $text = $this->loader->load($path);

        $chunks = $this->chunker->chunk($text);

        foreach ($chunks as $i => $chunk) {
            $vector = $this->embedding->getEmbedding($chunk, $provider, $model);
            $dimension = count($vector->toArray());

            $data = [
                'content' => $chunk,
                'source' => $path,
                'chunk_index' => $i,
            ];

            if ($dimension === 768) {
                $data['embedding_768'] = $vector;
            } elseif ($dimension === 1024) {
                $data['embedding_1024'] = $vector;
            } elseif ($dimension === 1536) {
                $data['embedding_1536'] = $vector;
            }

            Document::create($data);
        }
    }
}
