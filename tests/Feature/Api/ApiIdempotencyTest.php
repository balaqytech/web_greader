<?php

use App\Models\ApiIdempotencyKey;
use App\Models\BotContact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

function botContactPayload(array $overrides = []): array
{
    return array_merge([
        'channel' => 'whatsapp',
        'whatsapp' => '099123456',
        'sender_name' => 'Bot',
    ], $overrides);
}

it('rejects a mutating request with no idempotency key as a structured 409', function () {
    [, $token] = fasihServiceToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/bot-contacts', botContactPayload())
        ->assertStatus(409)
        ->assertJsonPath('error', 'idempotency_key_required');
});

it('rejects an idempotency key longer than 128 characters', function () {
    [, $token] = fasihServiceToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', str_repeat('a', 129))
        ->postJson('/api/v1/bot-contacts', botContactPayload())
        ->assertStatus(409)
        ->assertJsonPath('error', 'idempotency_key_required');
});

it('replays an identical request from the store without re-executing the controller', function () {
    [, $token] = fasihServiceToken();

    $first = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'k1')
        ->postJson('/api/v1/bot-contacts', botContactPayload());
    $first->assertCreated();

    $replay = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'k1')
        ->postJson('/api/v1/bot-contacts', botContactPayload());

    $replay->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($replay->getContent())->toBe($first->getContent())
        ->and(BotContact::count())->toBe(1)
        ->and(ApiIdempotencyKey::where('key', 'k1')->value('owner_token'))->toBeNull();
});

it('rejects an invalid bot-contact phone without persisting it', function () {
    [, $token] = fasihServiceToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'invalid-bot-phone')
        ->postJson('/api/v1/bot-contacts', botContactPayload(['whatsapp' => 'not-phone']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('whatsapp');

    expect(BotContact::count())->toBe(0);
});

it('returns a 409 conflict when the same key is reused for a different payload', function () {
    [, $token] = fasihServiceToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'k2')
        ->postJson('/api/v1/bot-contacts', botContactPayload())
        ->assertCreated();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'k2')
        ->postJson('/api/v1/bot-contacts', botContactPayload(['whatsapp' => '099999999']))
        ->assertStatus(409)
        ->assertJsonPath('error', 'idempotency_conflict');
});

it('isolates the same key across two different tokens', function () {
    [, $tokenA] = fasihServiceToken();
    [, $tokenB] = fasihServiceToken();

    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->withHeader('Idempotency-Key', 'shared')
        ->postJson('/api/v1/bot-contacts', botContactPayload(['whatsapp' => '099111111']))
        ->assertCreated();

    $this->app['auth']->forgetGuards();

    // Token B using the same key value is a wholly independent reservation.
    $this->withHeader('Authorization', "Bearer {$tokenB}")
        ->withHeader('Idempotency-Key', 'shared')
        ->postJson('/api/v1/bot-contacts', botContactPayload(['whatsapp' => '099222222']))
        ->assertCreated();

    expect(BotContact::count())->toBe(2);
});

it('returns a 409 with Retry-After while a reservation is still processing', function () {
    [$user, $plain] = fasihServiceToken();
    $tokenId = PersonalAccessToken::findToken($plain)->getKey();

    // Seed an active (unexpired, not completed) reservation for the key.
    ApiIdempotencyKey::create([
        'token_id' => $tokenId,
        'key' => 'inflight',
        'request_hash' => str_repeat('0', 64),
        'processing_at' => Carbon::now(),
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$plain}")
        ->withHeader('Idempotency-Key', 'inflight')
        ->postJson('/api/v1/bot-contacts', botContactPayload());

    $response->assertStatus(409)
        ->assertJsonPath('error', 'idempotency_in_progress')
        ->assertHeader('Retry-After');
});

it('takes over an abandoned reservation whose lease has expired', function () {
    [$user, $plain] = fasihServiceToken();
    $tokenId = PersonalAccessToken::findToken($plain)->getKey();

    // Seed an expired processing reservation (crashed request that never completed).
    ApiIdempotencyKey::create([
        'token_id' => $tokenId,
        'key' => 'abandoned',
        'request_hash' => str_repeat('0', 64),
        'processing_at' => Carbon::now()->subMinutes(10),
        'expires_at' => Carbon::now()->subMinutes(5),
    ]);

    $this->withHeader('Authorization', "Bearer {$plain}")
        ->withHeader('Idempotency-Key', 'abandoned')
        ->postJson('/api/v1/bot-contacts', botContactPayload())
        ->assertCreated();

    expect(BotContact::count())->toBe(1);
});

it('releases the reservation when the controller throws so the request can be retried', function () {
    Route::post('/_idem_throw', function () {
        throw new RuntimeException('boom');
    })->middleware('api.idempotency');

    $this->withHeader('Idempotency-Key', 'throwing')
        ->postJson('/_idem_throw')
        ->assertStatus(500);

    // The reservation was released, so nothing is left to block a retry.
    expect(ApiIdempotencyKey::count())->toBe(0);
});

it('does not let a stale owner complete a successor reservation', function () {
    Route::post('/_idem_stale_complete', function () {
        ApiIdempotencyKey::query()->firstOrFail()->update([
            'owner_token' => 'successor-owner',
            'processing_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json(['attempt' => 'stale'], 201);
    })->middleware('api.idempotency');

    $this->withHeader('Idempotency-Key', 'stale-complete')
        ->postJson('/_idem_stale_complete')
        ->assertCreated();

    $record = ApiIdempotencyKey::where('key', 'stale-complete')->firstOrFail();

    expect($record->owner_token)->toBe('successor-owner')
        ->and($record->response_status)->toBeNull()
        ->and($record->processing_at)->not->toBeNull();
});

it('does not let a stale owner delete a successor reservation after a server error', function () {
    Route::post('/_idem_stale_server_error', function () {
        ApiIdempotencyKey::query()->firstOrFail()->update([
            'owner_token' => 'successor-owner',
            'processing_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json(['error' => 'temporary'], 503);
    })->middleware('api.idempotency');

    $this->withHeader('Idempotency-Key', 'stale-server-error')
        ->postJson('/_idem_stale_server_error')
        ->assertServiceUnavailable();

    expect(ApiIdempotencyKey::where('key', 'stale-server-error')
        ->where('owner_token', 'successor-owner')
        ->exists())->toBeTrue();
});

it('does not let a stale owner release a successor reservation after an exception', function () {
    Route::post('/_idem_stale_exception', function () {
        ApiIdempotencyKey::query()->firstOrFail()->update([
            'owner_token' => 'successor-owner',
            'processing_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        throw new RuntimeException('stale attempt failed');
    })->middleware('api.idempotency');

    $this->withHeader('Idempotency-Key', 'stale-exception')
        ->postJson('/_idem_stale_exception')
        ->assertInternalServerError();

    expect(ApiIdempotencyKey::where('key', 'stale-exception')
        ->where('owner_token', 'successor-owner')
        ->exists())->toBeTrue();
});

it('prunes expired idempotency records via model:prune', function () {
    ApiIdempotencyKey::create([
        'token_id' => null,
        'key' => 'expired',
        'request_hash' => str_repeat('0', 64),
        'processing_at' => null,
        'response_status' => 200,
        'response_body' => '{}',
        'expires_at' => Carbon::now()->subDay(),
    ]);

    ApiIdempotencyKey::create([
        'token_id' => null,
        'key' => 'live',
        'request_hash' => str_repeat('1', 64),
        'processing_at' => null,
        'response_status' => 200,
        'response_body' => '{}',
        'expires_at' => Carbon::now()->addDay(),
    ]);

    $this->artisan('model:prune', ['--model' => [ApiIdempotencyKey::class]])->assertSuccessful();

    expect(ApiIdempotencyKey::pluck('key')->all())->toBe(['live']);
});
