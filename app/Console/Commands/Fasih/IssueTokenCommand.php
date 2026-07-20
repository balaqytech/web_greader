<?php

declare(strict_types=1);

namespace App\Console\Commands\Fasih;

use App\Models\User;
use App\Support\Api\FasihServiceAbilities;
use App\Support\Api\FasihServiceAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Issues the single credential the Fasih integration uses to reach the service API: a Sanctum
 * personal-access token, owned by a dedicated branchless `service_fasih` user, scoped to
 * exactly {@see FasihServiceAbilities::all()}, expiring after a configurable window.
 *
 * The command is deliberately strict about *which* user it will attach the service role and
 * token to. It creates the account if it does not exist, or safely reuses an existing one —
 * but only if that user is already a clean service principal. A user with any operational role
 * or a branch assignment is a human staff account; minting a service token against it would
 * silently hand API access to a panel user, so the command refuses.
 */
class IssueTokenCommand extends Command
{
    protected $signature = 'fasih:issue-token {email : The Fasih service-account email} {--revoke-existing : Delete the account\'s existing tokens before issuing the new one}';

    protected $description = 'Create or reuse the dedicated Fasih service account and issue a scoped Sanctum token.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if ($email === '') {
            $this->error('An email is required.');

            return self::FAILURE;
        }

        $serviceRole = Role::findOrCreate(FasihServiceAccount::Role, 'web');

        if ($serviceRole->permissions()->exists()) {
            $this->error('The service_fasih role has Shield permissions. Remove that drift before issuing a token.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = $this->createServiceUser($email);
        } elseif (! $this->isReusableServiceUser($user)) {
            return self::FAILURE;
        }

        $user->syncRoles([$serviceRole]);

        if ($this->option('revoke-existing')) {
            $revoked = $user->tokens()->delete();
            $this->info("Revoked {$revoked} existing token(s).");
        }

        $expiresAt = Carbon::now()->addDays($this->expiryDays());

        $token = $user->createToken(
            FasihServiceAccount::TokenName,
            FasihServiceAbilities::all(),
            $expiresAt,
        );

        $this->info('Fasih service token issued. This value is shown once — store it now:');
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->info('Abilities: '.implode(', ', FasihServiceAbilities::all()));
        $this->info('Expires:   '.$expiresAt->toDateTimeString());

        return self::SUCCESS;
    }

    private function createServiceUser(string $email): User
    {
        return User::create([
            'name' => 'Fasih Service',
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'branch_id' => null,
        ]);
    }

    /**
     * A pre-existing user may only be reused as the service principal if it is already a clean,
     * branchless service account. Any operational role or a branch assignment marks it as a
     * human staff account and disqualifies it.
     */
    private function isReusableServiceUser(User $user): bool
    {
        if ($user->branch_id !== null) {
            $this->error('That user is assigned to a branch and cannot be used as a service account.');

            return false;
        }

        if (! $user->hasRole(FasihServiceAccount::Role)) {
            $this->error('That email already belongs to a non-service user. Refusing to adopt it as the service account.');

            return false;
        }

        $unexpectedRoles = $user->getRoleNames()
            ->reject(fn (string $role): bool => $role === FasihServiceAccount::Role);

        if ($unexpectedRoles->isNotEmpty()) {
            $this->error('That user holds non-service role(s): '.$unexpectedRoles->implode(', ').'. Refusing to convert it into a service account.');

            return false;
        }

        if ($user->getAllPermissions()->isNotEmpty()) {
            $this->error('That user holds Shield permissions and cannot be used as the isolated service account.');

            return false;
        }

        return true;
    }

    private function expiryDays(): int
    {
        $days = (int) config('services.fasih.token_expiry_days', 90);

        return $days > 0 ? $days : 90;
    }
}
