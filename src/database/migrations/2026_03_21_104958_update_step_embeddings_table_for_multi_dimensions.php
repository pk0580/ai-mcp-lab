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
        Schema::table('step_embeddings', function (Blueprint $table) {
            // Переименовываем старую колонку embedding (1536) в embedding_1536
            if (Schema::hasColumn('step_embeddings', 'embedding')) {
                // В PostgreSQL renameColumn через Blueprint может потребовать doctrine/dbal
                // Но так как у нас pgvector, лучше использовать сырой SQL если есть сомнения,
                // либо убедиться что doctrine/dbal установлен.
                $table->renameColumn('embedding', 'embedding_1536');
            }

            // Добавляем новые колонки для различных размерностей эмбеддингов
            $table->vector('embedding_768', 768)->nullable();
            $table->vector('embedding_1024', 1024)->nullable();
        });

        // Делаем колонку embedding_1536 nullable если она не была таковой
        Schema::table('step_embeddings', function (Blueprint $table) {
             $table->vector('embedding_1536', 1536)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('step_embeddings', function (Blueprint $table) {
            if (Schema::hasColumn('step_embeddings', 'embedding_1536')) {
                $table->renameColumn('embedding_1536', 'embedding');
            }

            $table->dropColumn(['embedding_768', 'embedding_1024']);
        });
    }
};
