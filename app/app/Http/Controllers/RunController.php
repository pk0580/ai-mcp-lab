<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Services\Agents\NeuronAgent;
use App\Services\Tools\SearchTool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|min:3|max:5000',
        ]);

        $run = Run::create([
            'prompt' => $validated['prompt'],
            'status' => 'pending',
        ]);

        $agent = new NeuronAgent([
            new SearchTool(),
        ]);

        $agent->run($run);

        return response()->json($run->load('steps'), 201);
    }

    public function show(Run $run): JsonResponse
    {
        return response()->json($run->load('steps'));
    }
}
