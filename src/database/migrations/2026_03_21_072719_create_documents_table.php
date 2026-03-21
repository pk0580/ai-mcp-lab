<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->vector('embedding_768', 768)->nullable();
            $table->vector('embedding_1024', 1024)->nullable();
            $table->vector('embedding_1536', 1536)->nullable();
            $table->string('source');
            $table->integer('chunk_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
