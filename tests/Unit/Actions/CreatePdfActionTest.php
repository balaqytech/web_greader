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

it('resolves the returned PDF URL through the public disk even when the default disk is local', function () {
    config(['filesystems.default' => 'local']);
    // Storage::fake() does not preserve the disk's own 'url' config (it rebuilds the disk
    // config from scratch), so it must be passed back explicitly here or this test cannot
    // distinguish the 'public' disk's URL shape from the default disk's.
    Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);

    $mpdf = Mockery::mock(MpdfInstance::class);
    $mpdf->shouldReceive('output')->andReturn('pdf-bytes');
    LaravelMpdf::shouldReceive('loadView')->andReturn($mpdf);

    $path = 'pdfs/contracts/x.pdf';

    $url = app(CreatePdfAction::class)->execute('pdf.contract', $path, [
        'title' => 'title',
        'contract' => 'body',
        'signature' => 'signature-url',
    ]);

    // Storage::url() on the default ('local') disk resolves through a different serving
    // route than 'public' and would 404 for a file that only exists on 'public'.
    expect($url)->toBe(Storage::disk('public')->url($path))
        ->and($url)->not->toBe(Storage::url($path));
});
