<?php

namespace App\Actions\Support;

use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class CreatePdfAction
{
    public function execute(string $view, string $path, array $data): string
    {
        // Get the PDF content and store it using Laravel's Storage
        $pdfContent = PDF::loadView($view, $data, [], [
            'setAutoTopMargin' => 'pad',
            'setAutoBottomMargin' => 'pad',
        ])->output();

        Storage::disk('public')->put($path, $pdfContent);

        return Storage::url($path);
    }
}
