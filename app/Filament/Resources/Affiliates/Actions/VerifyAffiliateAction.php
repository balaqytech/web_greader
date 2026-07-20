<?php

namespace App\Filament\Resources\Affiliates\Actions;

use App\Models\Affiliate;
use App\Services\Fasih\FasihClient;
use App\States\Affiliates\Verified;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

            $this->sendWebhook($record);
        });
    }

    /**
     * The transport lives entirely behind {@see FasihClient}; this action knows nothing about
     * endpoints or HTTP details. Registered after commit and its failure caught and
     * reported, so a notification outage cannot roll back or misreport a completed verification.
     */
    private function sendWebhook(Affiliate $affiliate): void
    {
        if (! config('services.fasih.affiliate_verified.enabled') || ! app()->environment('production')) {
            return;
        }

        $payload = [
            'id' => $affiliate->id,
            'name' => $affiliate->name,
            'code' => $affiliate->code,
            'whatsapp' => $affiliate->whatsapp,
            'affiliate_url' => 'https://g-reader-school.com?ref='.$affiliate->code,
            'login_url' => 'https://web.g-reader-school.com/affiliate/login',
        ];

        DB::afterCommit(function () use ($payload) {
            try {
                app(FasihClient::class)->affiliateVerified($payload);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
