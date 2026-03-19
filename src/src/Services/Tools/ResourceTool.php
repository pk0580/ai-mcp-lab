<?php

namespace App\Services\Tools;

use App\Mcp\Resources\ProjectResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Laravel\Mcp\Request as McpRequest;
use Stringable;

class ResourceTool implements ToolInterface
{
    public function getName(): string
    {
        return 'read_project_description';
    }

    public function description(): Stringable|string
    {
        return 'Reads the project description from the internal project resource.';
    }

    public function handle(Request $request): Stringable|string
    {
        $resource = new ProjectResource();
        $response = $resource->handle(new McpRequest());

        return (string) $response->content();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
            'required' => [],
        ];
    }
}
