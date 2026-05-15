<?php

namespace App\Actions\Leads;

use App\Enums\LeadContactMethod;
use App\Models\Affiliate;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\Services\LeadDuplicateResolver;
use App\States\Leads\ContactedLead;
use App\Support\LeadIdentityNormalizer;
use Illuminate\Database\QueryException;
use Spatie\WebhookServer\WebhookCall;

final class CreateLeadAction
{
    public function __construct(
        private LeadDuplicateResolver $duplicateResolver,
        private LeadIdentityNormalizer $normalizer,
    ) {}

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
        $whatsapp = normalize_phone_number(
            convert_eastern_arabic_to_arabic($whatsapp),
        );

        $program = Program::findOrFail($program_id);

        $season = Season::current($program->type);

        $affiliate = $this->resolveAffiliate($affiliate_code);

        $values = [
            'guardian_name' => $guardian_name,
            'source' => $source,
            'program_type' => $program->type,
            'data' => $data,
            'affiliate_id' => $affiliate?->id,
            'affiliate_code_snapshot' => $affiliate?->code,
        ];

        $existing = $this->duplicateResolver->findExisting(
            whatsapp: $whatsapp,
            studentName: $student_name,
            programId: $program->id,
            seasonId: $season->id,
            branchId: $branch_id,
        );

        if ($existing !== null) {
            $values['student_name'] = $this->normalizer->preferLongerDisplayName(
                $existing->student_name,
                $student_name,
            );

            $existing->fill($values);
            $existing->save();

            $lead = $existing->fresh();
        } else {
            try {
                $lead = Lead::create([
                    'whatsapp' => $whatsapp,
                    'student_name' => $student_name,
                    'program_id' => $program->id,
                    'season_id' => $season->id,
                    'branch_id' => $branch_id,
                    ...$values,
                ]);
            } catch (QueryException $e) {
                if (! $this->isUniqueConstraintViolation($e)) {
                    throw $e;
                }

                $lead = $this->duplicateResolver->findExisting(
                    whatsapp: $whatsapp,
                    studentName: $student_name,
                    programId: $program->id,
                    seasonId: $season->id,
                    branchId: $branch_id,
                );

                if ($lead === null) {
                    throw $e;
                }

                $values['student_name'] = $this->normalizer->preferLongerDisplayName(
                    $lead->student_name,
                    $student_name,
                );

                $lead->fill($values);
                $lead->save();
                $lead = $lead->fresh();
            }
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

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        if ((string) $exception->getCode() === '23000') {
            return true;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed');
    }
}
