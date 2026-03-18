<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->onDelete('cascade');
            $table->foreignId('step_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('level')->default('info'); // info, debug, warning, error
            $table->string('category')->nullable(); // reasoning, tool_call, memory_retrieval, etc.
            $table->text('message');
            $table->json('context')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_steps');
    }
};
