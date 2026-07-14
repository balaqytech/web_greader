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

        // Must resolve through the same disk the file was actually written to. The default
        // disk (`local`) resolves URLs through its own local-serving route, which points at a
        // different root directory and would silently 404 for a file that only exists on
        // `public`.
        return Storage::disk('public')->url($path);
    }
}
