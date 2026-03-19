<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Resource;

#[Description('Returns the project description from README.md')]
class ProjectResource extends Resource
{
    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        $path = base_path('README.md');

        if (!file_exists($path)) {
            return Response::text('Project description not found. Path: ' . $path);
        }

        return Response::text(file_get_contents($path));
    }
}
