<?php

namespace App\Filament\Resources\Affiliates\Actions;

use App\States\Affiliates\Rejected;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class RejectAffiliateAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reject';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('admin.affiliate.reject'));
        $this->icon('heroicon-o-x-circle');
        $this->color('danger');
        $this->requiresConfirmation();
        $this->visible(fn($record) => $record->status->canTransitionTo(Rejected::class, Auth::user()));
        $this->action(function ($record) {
            $record->status->transitionTo(Rejected::class, Auth::user());
        });
    }
}
