<?php

namespace App\Actions\Support;

use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use RuntimeException;

class CreatePdfAction
{
    public function execute(string $view, string $path, array $data): string
    {
        $pdfContent = PDF::loadView($view, $data, [], [
            'setAutoTopMargin' => 'pad',
            'setAutoBottomMargin' => 'pad',
        ])->output();

        if (! Storage::disk('public')->put($path, $pdfContent)) {
            throw new RuntimeException("Failed to write generated PDF to storage path [{$path}].");
        }

        return Storage::url($path);
    }
}
