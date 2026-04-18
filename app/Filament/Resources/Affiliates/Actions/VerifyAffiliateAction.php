<?php

namespace App\Filament\Resources\Affiliates\Actions;

use App\Models\Affiliate;
use App\States\Affiliates\Verified;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class VerifyAffiliateAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'verify';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.affiliate.verify'));
        $this->icon('heroicon-o-check-circle');
        $this->color('success');
        $this->requiresConfirmation();

        $this->visible(
            fn (?Affiliate $record): bool => $record?->status?->canTransitionTo(Verified::class, Auth::user()) ?? false
        );

        $this->action(function (Affiliate $record) {
            $record->status->transitionTo(Verified::class, Auth::user());
        });
    }
}
