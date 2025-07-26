<?php

namespace App\Actions\Support;

use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class CreatePdfAction
{
    public function execute(string $view, string $path, array $data): string
    {
        PDF::loadView($view, $data, [], [
            'setAutoTopMargin' => 'pad',
            'setAutoBottomMargin' => 'pad',
        ])
            ->save(Storage::disk('public')->path($path));

        return Storage::url($path);
    }
}
