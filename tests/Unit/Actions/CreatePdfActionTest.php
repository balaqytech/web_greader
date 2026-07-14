<?php

use App\Actions\Support\CreatePdfAction;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
use Mccarlosen\LaravelMpdf\LaravelMpdf as MpdfInstance;
use Tests\TestCase;

uses(TestCase::class);

it('throws and returns no URL when the rendered PDF fails to persist to storage', function () {
    $mpdf = Mockery::mock(MpdfInstance::class);
    $mpdf->shouldReceive('output')->andReturn('pdf-bytes');
    LaravelMpdf::shouldReceive('loadView')->andReturn($mpdf);

    $fakeDisk = Mockery::mock(Cloud::class);
    $fakeDisk->shouldReceive('put')->andReturn(false);
    Storage::shouldReceive('disk')->with('public')->andReturn($fakeDisk);

    expect(fn () => app(CreatePdfAction::class)->execute('pdf.contract', 'pdfs/contracts/x.pdf', [
        'title' => 'title',
        'contract' => 'body',
        'signature' => 'signature-url',
    ]))->toThrow(RuntimeException::class);
});
