<?php

declare(strict_types=1);

namespace App\Console\Commands\Payments;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Groundwork for the chatbot/guardian-facing payment API: mints a Sanctum token scoped to
 * exactly the two payment abilities, for an existing service-account user.
 *
 * Deliberately minimal — there is no dedicated service-account model or self-service issuance
 * flow here, which is Phase 5 scope. This exists so the abilities this phase actually
 * implements (`payments:initiate`, `payments:upload-receipt`) have a real way to be issued
 * without inventing unrelated infrastructure ahead of when it is needed.
 */
class CreateApiTokenCommand extends Command
{
    protected $signature = 'payments:create-api-token {email : The service-account user\'s email}';

    protected $description = 'Create a Sanctum token scoped to payments:initiate and payments:upload-receipt for an existing user.';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email exists. Create the service-account user first.');

            return self::FAILURE;
        }

        $token = $user->createToken('payments-api', ['payments:initiate', 'payments:upload-receipt']);

        $this->info('Token created. This value is shown once — store it now:');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
