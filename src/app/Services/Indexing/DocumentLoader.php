<?php

namespace App\Services\Indexing;

use Illuminate\Support\Facades\File;
use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;

class DocumentLoader
{
    /**
     * Загружает содержимое файла.
     */
    public function load(string $path): string
    {
        if (!File::exists($path)) {
            throw new \InvalidArgumentException("Файл не найден: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return $this->parsePdf($path);
        }

        if (in_array($extension, ['docx', 'doc'])) {
            return $this->parseDocx($path);
        }

        return File::get($path);
    }

    /**
     * Извлекает текст из PDF-файла.
     */
    protected function parsePdf(string $path): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        return $pdf->getText();
    }

    /**
     * Извлекает текст из DOCX-файла.
     */
    protected function parseDocx(string $path): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException("Расширение ext-zip не установлено. Не удалось прочитать DOCX файл: {$path}");
        }

        $phpWord = IOFactory::load($path);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text .= $this->extractTextFromElement($element);
            }
        }
        return $text;
    }

    /**
     * Рекурсивно извлекает текст из элемента PHPWord.
     */
    protected function extractTextFromElement($element): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $t = $element->getText();
            if (is_string($t)) {
                $text .= $t . "\n";
            } elseif (is_array($t)) {
                foreach ($t as $part) {
                    if (is_string($part)) {
                        $text .= $part;
                    }
                }
                $text .= "\n";
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $childElement) {
                $text .= $this->extractTextFromElement($childElement);
            }
        }

        return $text;
    }
}
