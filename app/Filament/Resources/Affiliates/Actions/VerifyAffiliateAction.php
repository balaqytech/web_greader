<?php

namespace App\Filament\Resources\Affiliates\Actions;

use App\Models\Affiliate;
use App\States\Affiliates\Verified;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Spatie\WebhookServer\WebhookCall;

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
            fn(?Affiliate $record): bool => $record?->status?->canTransitionTo(Verified::class, Auth::user()) ?? false
        );

        $this->action(function (Affiliate $record) {
            $record->status->transitionTo(Verified::class, Auth::user());

            $this->sendWebhook($record);
        });
    }

    private function sendWebhook(Affiliate $affiliate): void
    {
        if (config('services.webhooks.affiliate.enabled') && app()->environment('production')) {
            WebhookCall::create()
                ->url(config('services.webhooks.affiliate.verified_url'))
                ->payload([
                    'id' => $affiliate->id,
                    'name' => $affiliate->name,
                    'code' => $affiliate->code,
                    'whatsapp' => $affiliate->whatsapp,
                    'affiliate_url' => 'https://g-reader-school.com?ref=' . $affiliate->code,
                    'login_url' => 'https://web.g-reader-school.com/affiliate/login',
                ])
                ->doNotSign()
                ->dispatch();
        }
    }
}
