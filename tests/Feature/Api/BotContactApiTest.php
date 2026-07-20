<?php

use App\Http\Controllers\Api\V1\BotContactController;
use App\Http\Requests\Api\V1\StoreBotContactRequest;
use Illuminate\Support\Facades\Exceptions;

use function Pest\Laravel\mock;

it('reports unexpected persistence failures without exposing their details', function () {
    Exceptions::fake();
    $request = mock(StoreBotContactRequest::class)->makePartial();
    $request->shouldReceive('validated')->once()->andReturn([
        'channel' => 'whatsapp',
        'whatsapp' => '+96899123456',
        'additional_data' => [
            'invalid' => new class implements JsonSerializable
            {
                public function jsonSerialize(): mixed
                {
                    throw new RuntimeException('sensitive persistence detail');
                }
            },
        ],
    ]);

    $response = app(BotContactController::class)->store($request);
    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(500)
        ->and($payload['error'])->toBe('server_error')
        ->and($payload['message'])->toBe(__('alerts.api.unexpected_error'))
        ->and(json_encode($payload))->not->toContain('sensitive persistence detail');

    Exceptions::assertReported(RuntimeException::class);
});
