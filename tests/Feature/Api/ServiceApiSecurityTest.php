<?php

use App\Models\Affiliate;
use App\Models\ApiIdempotencyKey;
use App\Models\Branch;
use App\Models\Program;
use App\Models\User;
use App\Support\Api\FasihServiceAbilities;
use App\Support\Api\FasihServiceAccount;
use App\Support\Auditing\ApiAbilityResolver;
use App\Support\Auditing\ApiTokenIdResolver;
use Filament\Facades\Filament;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use OwenIt\Auditing\Facades\Auditor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Panel denial
|--------------------------------------------------------------------------
*/

it('bars the service_fasih account from the admin panel even if Access:Panel is granted', function () {
    Role::findOrCreate(FasihServiceAccount::Role, 'web');
    Permission::findOrCreate('Access:Panel', 'web');

    $user = User::factory()->create(['branch_id' => null]);
    $user->assignRole(FasihServiceAccount::Role);
    $user->givePermissionTo('Access:Panel');

    $panel = Filament::getPanel('admin');

    expect($user->fresh()->canAccessPanel($panel))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Public catalogs
|--------------------------------------------------------------------------
*/

it('keeps branch and program catalogs public with no token required', function () {
    Branch::factory()->create();
    Program::factory()->create();

    $this->getJson('/api/v1/branches')->assertOk();
    $this->getJson('/api/v1/programs')->assertOk();
});

/*
|--------------------------------------------------------------------------
| 401 / 403 matrix
|--------------------------------------------------------------------------
*/

it('rejects a protected route with no token as 401', function () {
    $this->getJson('/api/v1/bot-contacts')->assertUnauthorized();
});

it('rejects a token owned by a non-service user with 403', function () {
    // A plain user with the exact abilities but WITHOUT the service role must not pass.
    $user = User::factory()->create(['branch_id' => null]);
    $token = $user->createToken(FasihServiceAccount::TokenName, FasihServiceAbilities::all())->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/bot-contacts')
        ->assertForbidden();
});

it('rejects a service-role token when its owner is assigned to a branch', function () {
    Role::findOrCreate(FasihServiceAccount::Role, 'web');
    $user = User::factory()->create(['branch_id' => Branch::factory()]);
    $user->assignRole(FasihServiceAccount::Role);
    $token = $user->createToken(FasihServiceAccount::TokenName, FasihServiceAbilities::all())->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/bot-contacts')
        ->assertForbidden();
});

it('rejects a service-role token when its owner also has another role', function () {
    Role::findOrCreate(FasihServiceAccount::Role, 'web');
    Role::findOrCreate('branch_manager', 'web');
    $user = User::factory()->create(['branch_id' => null]);
    $user->assignRole([FasihServiceAccount::Role, 'branch_manager']);
    $token = $user->createToken(FasihServiceAccount::TokenName, FasihServiceAbilities::all())->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/bot-contacts')
        ->assertForbidden();
});

it('rejects a service-role token when its owner has a Shield permission', function () {
    Role::findOrCreate(FasihServiceAccount::Role, 'web');
    Permission::findOrCreate('Access:Panel', 'web');
    $user = User::factory()->create(['branch_id' => null]);
    $user->assignRole(FasihServiceAccount::Role);
    $user->givePermissionTo('Access:Panel');
    $token = $user->createToken(FasihServiceAccount::TokenName, FasihServiceAbilities::all())->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/bot-contacts')
        ->assertForbidden();
});

it('rejects a service token that lacks the exact ability with 403', function () {
    [, $token] = fasihServiceToken([FasihServiceAbilities::LeadsRead]);

    // Has leads:read, but bot-contacts requires bot-contacts:manage.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/bot-contacts')
        ->assertForbidden();
});

it('rejects a service-account token with the wrong token name or an unknown ability', function (string $name, array $abilities) {
    Role::findOrCreate(FasihServiceAccount::Role, 'web');
    $user = User::factory()->create(['branch_id' => null]);
    $user->assignRole(FasihServiceAccount::Role);
    $token = $user->createToken($name, $abilities)->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/bot-contacts')
        ->assertForbidden();
})->with([
    'wrong name' => ['manual-token', FasihServiceAbilities::all()],
    'wildcard ability' => [FasihServiceAccount::TokenName, ['*']],
    'unknown ability' => [FasihServiceAccount::TokenName, ['unknown:ability']],
]);

it('allows a fully-scoped service token through', function () {
    [, $token] = fasihServiceToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/bot-contacts')
        ->assertOk();
});

it('checks the token ability before requiring an idempotency key', function () {
    [, $token] = fasihServiceToken([FasihServiceAbilities::LeadsRead]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/bot-contacts', [
            'channel' => 'whatsapp',
            'whatsapp' => '099123456',
        ])
        ->assertForbidden();

    expect(ApiIdempotencyKey::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Per-token rate isolation
|--------------------------------------------------------------------------
*/

it('isolates the read rate limit per token', function () {
    [, $tokenA] = fasihServiceToken();
    [, $tokenB] = fasihServiceToken();

    // Exhaust token A's 60/min read budget.
    for ($i = 0; $i < 60; $i++) {
        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/v1/leads?whatsapp=099123456')
            ->assertOk();
    }

    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->getJson('/api/v1/leads?whatsapp=099123456')
        ->assertStatus(429);

    // Within a single test the sanctum guard memoizes the first token's user; forget it so the
    // next request re-authenticates as token B rather than reusing token A's identity.
    $this->app['auth']->forgetGuards();

    // Token B is untouched — a separate per-token bucket.
    $this->withHeader('Authorization', "Bearer {$tokenB}")
        ->getJson('/api/v1/leads?whatsapp=099123456')
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Audit attribution resolvers
|--------------------------------------------------------------------------
*/

it('resolves the api token id from the active personal-access token', function () {
    [$user, $plain] = fasihServiceToken([FasihServiceAbilities::LeadsCreate]);
    $pat = PersonalAccessToken::findToken($plain);

    Auth::guard('sanctum')->setUser($user->withAccessToken($pat));

    expect(ApiTokenIdResolver::resolve(new Affiliate))->toBe($pat->getKey());
});

it('returns a null api token id when there is no personal-access token', function () {
    expect(ApiTokenIdResolver::resolve(new Affiliate))->toBeNull();
});

it('resolves the api ability from the route middleware when a token is present', function () {
    [$user, $plain] = fasihServiceToken([FasihServiceAbilities::LeadsCreate]);
    $pat = PersonalAccessToken::findToken($plain);

    Auth::guard('sanctum')->setUser($user->withAccessToken($pat));

    $route = new RoutingRoute(['POST'], 'x', []);
    $route->middleware('abilities:leads:create');
    request()->setRouteResolver(fn () => $route);

    expect(ApiAbilityResolver::resolve(new Affiliate))->toBe('leads:create');
});

it('records api attribution on an audited change made under a service token', function () {
    [$user, $plain] = fasihServiceToken([FasihServiceAbilities::LeadsCreate]);
    $pat = PersonalAccessToken::findToken($plain);

    Auth::guard('sanctum')->setUser($user->withAccessToken($pat));

    $affiliate = Affiliate::factory()->create();
    $affiliate->auditEvent = 'updated';
    $affiliate->isCustomEvent = true;
    $affiliate->auditCustomOld = ['name' => 'before'];
    $affiliate->auditCustomNew = ['name' => 'after'];

    Auditor::execute($affiliate);

    $audit = $affiliate->audits()->latest('id')->first();

    expect($audit->api_token_id)->toBe($pat->getKey());
});
