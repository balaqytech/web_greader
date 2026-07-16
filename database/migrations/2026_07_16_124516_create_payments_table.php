<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registration-fee payment attempts.
 *
 * One row per *attempt*, not per application: a declined, rejected or expired attempt is
 * kept and a retry creates a new row, so the history of what was tried survives. The
 * invariants "at most one active attempt per application" and "at most one paid attempt per
 * application" are enforced by locking the application row (see `App\Support\Payments\
 * LockPayment` and the lock order documented on the Payment model), not by a database
 * constraint — a partial/generated-column unique index expressing "one paid per application"
 * is not portable across this project's SQLite test engine and MySQL/MariaDB, and a
 * constraint that only exists on one of them is worse than none: it would pass tests while
 * being absent in production.
 *
 * `restrictOnDelete` throughout: a payment is financial evidence. Deleting an application or
 * a branch must fail loudly rather than silently cascade away the record that money changed
 * hands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // The public identifier. A ULID rather than the auto-increment id so references
            // handed to guardians, the chatbot, and the provider cannot be enumerated or
            // probed to discover how many payments exist or to guess a neighbour's.
            $table->ulid('reference')->unique();

            $table->foreignId('application_id')
                ->constrained()
                ->restrictOnDelete();

            // Denormalised from the application so BranchScope can filter payments without
            // joining, and so a payment's branch is a fixed historical fact rather than
            // something that silently moves if an application is ever re-branched.
            $table->foreignId('branch_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('purpose');
            $table->string('method');
            $table->string('status');

            // The fee as it was when this attempt was created — never read live from
            // settings afterwards. An administrator changing the fee must not retroactively
            // alter what an in-flight or historical attempt was for. decimal(12,3) matches
            // OMR's real precision (1 OMR = 1000 baisa) and OmrAmount's supported range;
            // string-cast on the model, so it never becomes a float.
            $table->decimal('amount', 12, 3);
            $table->char('currency', 3);

            // Namespaced by the acting token/user before it is stored, so one caller's key
            // can never collide with — or replay — another's.
            $table->string('idempotency_key')->nullable()->unique();

            // Distinguishes an exact replay (same key, same request → return the existing
            // result) from a conflicting reuse of a key (same key, different request →
            // refuse, rather than hand back someone else's payment).
            $table->string('request_hash')->nullable();

            $table->string('provider_session_id')->nullable();
            $table->text('provider_checkout_url')->nullable();
            $table->timestamp('provider_expires_at')->nullable();

            // Sanitised provider response — never the raw payload, which carries keys and
            // internals that must not be persisted or surfaced.
            $table->json('provider_payload')->nullable();

            $table->string('receipt_path')->nullable();

            // Failure is technical/provider-decided; rejection is a human finance decision
            // and always carries a reason. Kept apart so "the gateway said no" and "a person
            // said no" never get conflated.
            $table->text('failure_reason')->nullable();
            $table->text('rejection_reason')->nullable();

            // The finance actor behind a verify-or-reject decision on a bank receipt, and
            // when. Which of the two it was is carried by the resulting state.
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->string('cash_reference')->nullable();
            $table->text('cash_notes')->nullable();

            // Nullable: an attempt initiated through the chatbot API is created by a service
            // token, which has no user behind it.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index('provider_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
