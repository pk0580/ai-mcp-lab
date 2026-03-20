<?php

namespace App\Ai\Tools;

use App\Ai\Attributes\Description;
use App\Mcp\Resources\ProjectResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request as McpRequest;
use Stringable;

class ResourceTool implements ToolInterface
{
    public function getName(): string
    {
        return 'read_project_description';
    }

    #[Description('Reads the project description from the internal project resource.')]
    public function handle(): Stringable|string
    {
        $resource = new ProjectResource();
        $response = $resource->handle(new McpRequest());

        return (string) $response->content();
    }

    public function description(): Stringable|string
    {
        return 'Reads the project description from the internal project resource.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
