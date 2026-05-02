<?php

namespace App\Actions\Leads;

use App\Enums\LeadContactMethod;
use App\Exceptions\AffiliateNotVerifiedException;
use App\Models\Affiliate;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\States\Leads\ContactedLead;
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
        $lead = Lead::firstOrCreate([
            'whatsapp' => $whatsapp,
            'program_id' => $program->id,
            'season_id' => $season->id,
            'branch_id' => $branch_id,
            'student_name' => $student_name,
            'guardian_name' => $guardian_name,
        ], [
            'whatsapp' => $whatsapp,
            'guardian_name' => $guardian_name,
            'student_name' => $student_name,
            'program_id' => $program->id,
            'branch_id' => $branch_id,
            'source' => $source,
            'program_type' => $program->type,
            'season_id' => $season->id,
            'data' => $data,
            'affiliate_id' => $affiliate?->id,
            'affiliate_code_snapshot' => $affiliate?->code,
        ]);

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
