<?php

namespace App\Http\Controllers;

use App\Jobs\RunAgentJob;
use App\Models\Run;
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

        RunAgentJob::dispatch($run);

        return response()->json($run->load('steps'), 201);
    }

    public function show(Run $run): JsonResponse
    {
        return response()->json($run->load('steps'));
    }
}
