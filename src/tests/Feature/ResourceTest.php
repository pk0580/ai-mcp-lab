<?php

namespace Tests\Feature;

use App\Ai\Tools\ResourceTool;
use App\Mcp\Resources\ProjectResource;
use Laravel\Mcp\Request as McpRequest;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    public function test_project_resource_returns_description()
    {
        $resource = new ProjectResource();
        $response = $resource->handle(new McpRequest());

        $this->assertStringContainsString('Laravel is a web application framework', (string) $response->content());
    }

    public function test_resource_tool_can_be_handled()
    {
        $tool = new ResourceTool();
        $result = $tool->handle();

        $this->assertStringContainsString('Laravel is a web application framework', (string) $result);
    }
}
