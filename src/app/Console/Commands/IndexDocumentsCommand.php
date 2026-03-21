<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Services\Indexing\DocumentIndexer;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class IndexDocumentsCommand extends Command
{
    protected $signature = 'app:index-documents {path : Путь к файлу или директории} {--provider= : Провайдер эмбеддингов} {--model= : Модель эмбеддингов}';
    protected $description = 'Индексация документов и сохранение эмбеддингов в базу данных';

    /**
     * Execute the console command.
     */
    public function handle(DocumentIndexer $indexer): int
    {
        $path = $this->argument('path');
        $this->info("Переданный путь: {$path}");
        $fullPath = base_path($path);
        $this->info("Вычисленный полный путь: {$fullPath}");

        if (!File::exists($fullPath)) {
            $this->error("Путь не найден: {$fullPath}");
            return Command::FAILURE;
        }

        if (File::isDirectory($fullPath)) {
            $files = collect(File::allFiles($fullPath))
                ->map(fn($file) => $file->getRealPath());
        } else {
            $files = collect([realpath($fullPath)]);
        }

        $this->info("Начинаем индексацию " . $files->count() . " файлов...");

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $this->line("Индексируем: " . $filePath);
            try {
                $indexer->index($filePath, $this->option('provider'), $this->option('model'));
                $this->info("Файл {$fileName} проиндексирован.");
            } catch (\Exception $e) {
                $this->error("Ошибка при индексации {$fileName}: " . $e->getMessage());
            }
        }

        $this->info("Индексация завершена.");
        return Command::SUCCESS;
    }
}
