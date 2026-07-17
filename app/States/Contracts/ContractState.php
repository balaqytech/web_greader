<?php

declare(strict_types=1);

namespace App\States\Contracts;

use App\States\Contracts\Transitions\GeneratedToCancelled;
use App\States\Contracts\Transitions\GeneratedToSigned;
use App\States\Contracts\Transitions\GeneratedToSuperseded;
use App\States\Contracts\Transitions\SignedToCancelled;
use App\States\Contracts\Transitions\SignedToSuperseded;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Versioned contract lifecycle (§3.5).
 *
 *   [*]        -> Generated   version N created from an immutable snapshot
 *   Generated  -> Signed      e-signature or uploaded signed copy
 *   Generated  -> Superseded  regeneration (data reopened / changed)
 *   Generated  -> Cancelled   application cancelled
 *   Signed     -> Superseded  contract-relevant correction -> new version
 *   Signed     -> Cancelled   application cancelled
 *
 * Superseded and Cancelled are terminal. A contract never leaves them: history is retained,
 * so a regenerated or cancelled version is kept exactly as it was, its token invalidated, and
 * a fresh version takes over. Signing requires a persisted signed artifact (guarded in
 * GeneratedToSigned); supersede/cancel invalidate the token and expiry while keeping the
 * stored files.
 */
abstract class ContractState extends State implements HasColor, HasLabel
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Generated::class)
            ->allowTransition(Generated::class, Signed::class, GeneratedToSigned::class)
            ->allowTransition(Generated::class, Superseded::class, GeneratedToSuperseded::class)
            ->allowTransition(Generated::class, Cancelled::class, GeneratedToCancelled::class)
            ->allowTransition(Signed::class, Superseded::class, SignedToSuperseded::class)
            ->allowTransition(Signed::class, Cancelled::class, SignedToCancelled::class);
    }

    /**
     * The states an active version can still be in — exactly one contract per application may
     * be in one of these at a time. Declared once and reused by the model's `activeContract`
     * relation and its query scopes so the in-memory rule and the SQL rule cannot drift apart.
     *
     * @return list<class-string<ContractState>>
     */
    public static function activeStates(): array
    {
        return [Generated::class, Signed::class];
    }

    public function isActive(): bool
    {
        foreach (static::activeStates() as $state) {
            if ($this instanceof $state) {
                return true;
            }
        }

        return false;
    }
}
