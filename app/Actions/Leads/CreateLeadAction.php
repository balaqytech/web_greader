<?php

namespace App\Actions\Leads;

use App\Enums\LeadContactMethod;
use App\Exceptions\AffiliateNotVerifiedException;
use App\Models\Affiliate;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\States\Leads\ContactedLead;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\WebhookServer\WebhookCall;

final class CreateLeadAction
{
    public function execute(
        string $whatsapp,
        string $guardian_name,
        string $student_name,
        int $program_id,
        int $branch_id,
        string $source,
        array $data = [],
        ?string $affiliate_code = null,
    ): Lead {
        $program = Program::findOrFail($program_id);

        $season = Season::current($program->type);

        $affiliate = $this->resolveAffiliate($affiliate_code);

        // prevent duplicate lead
        $attributes = [
            'whatsapp'   => $whatsapp,
            'program_id' => $program->id,
            'season_id'  => $season->id,
            'branch_id'  => $branch_id,
            'student_name' => $student_name,
        ];

        $values = [
            'guardian_name' => $guardian_name,
            'source'        => $source,
            'program_type'  => $program->type,
            'data'          => $data,
            'affiliate_id'  => $affiliate?->id,
            'affiliate_code_snapshot' => $affiliate?->code,
        ];

        try {
            return Lead::updateOrCreate($attributes, $values);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return Lead::where($attributes)->firstOrFail();
            }
            throw $e;
        }

        $this->dispatchWebhookIfNeeded($lead);

        return $lead;
    }

    private function resolveAffiliate(?string $affiliateCode): ?Affiliate
    {
        if (! $affiliateCode) {
            return null;
        }

        $affiliate = Affiliate::where('code', $affiliateCode)->first();

        if (! $affiliate) {
            return null;
        }

        // if (! $affiliate->isVerified()) {
        //     throw new AffiliateNotVerifiedException($affiliateCode);
        // }

        return $affiliate;
    }

    private function dispatchWebhookIfNeeded(Lead $lead): void
    {
        if (! $this->shouldDispatchWebhook()) {
            return;
        }

        $lead->status->transitionTo(
            ContactedLead::class,
            contactedBy: 'whatsapp_bot',
            contactMethod: LeadContactMethod::Whatsapp,
        );

        WebhookCall::create()
            ->url(config('services.webhooks.lead.created_url'))
            ->payload($lead->fresh()->toArray())
            ->useSecret(config('services.webhooks.secret'))
            ->dispatch();
    }

    private function shouldDispatchWebhook(): bool
    {
        return config('services.webhooks.lead.enabled')
            && app()->environment('production');
    }
}
