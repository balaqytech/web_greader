<?php

use App\Models\User;
use App\Support\Api\FasihServiceAbilities;
use App\Support\Api\FasihServiceAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a branchless Fasih service-account user (with the `service_fasih` role) and issue a
 * real Sanctum personal-access token scoped to the given abilities. Returns the token user and
 * the plaintext token — the credential every protected `/api/v1` service route now requires.
 *
 * @param  list<string>|null  $abilities  Defaults to the full canonical ability set.
 * @return array{0: User, 1: string}
 */
function fasihServiceToken(?array $abilities = null): array
{
    $abilities ??= FasihServiceAbilities::all();

    Role::findOrCreate(FasihServiceAccount::Role, 'web');

    $user = User::factory()->create(['branch_id' => null]);
    $user->assignRole(FasihServiceAccount::Role);

    return [$user, $user->createToken('test', $abilities)->plainTextToken];
}
