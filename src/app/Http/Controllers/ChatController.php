<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Models\Step;
use App\Jobs\StepJob;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Ai\Agents\NeuronAgent;
use App\Mcp\McpRegistry;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function index(): Factory|View
    {
        return view('chat');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string',
        ]);

        $run = Run::create([
            'prompt' => $request->prompt,
            'agent_type' => 'neuron',
            'status' => 'pending',
        ]);

        StepJob::dispatch($run);

        return response()->json($run);
    }

    public function show(Run $run): JsonResponse
    {
        return response()->json([
            'run' => $run,
            'steps' => $run->steps()->orderBy('id', 'asc')->get(),
        ]);
    }

    public function stream(Run $run): StreamedResponse
    {
        return response()->stream(function () use ($run) {
            $lastStepId = 0;
            $attempts = 0;
            $maxAttempts = 300; // ~5 минут при задержке 1 сек

            while ($attempts < $maxAttempts) {
                $run->refresh();

                $newSteps = $run->steps()
                    ->where('id', '>', $lastStepId)
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($newSteps as $step) {
                    echo "data: " . json_encode($step) . "\n\n";
                    $lastStepId = $step->id;
                }

                if ($run->status === 'completed' || $run->status === 'failed') {
                    echo "data: " . json_encode(['status' => $run->status]) . "\n\n";
                    break;
                }

                if (connection_aborted()) {
                    break;
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                sleep(1);
                $attempts++;
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
