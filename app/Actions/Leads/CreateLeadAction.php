<?php

namespace App\Actions\Leads;

use App\Enums\LeadContactMethod;
use App\Exceptions\InvalidSeasonForProgramException;
use App\Models\Affiliate;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;
use App\Services\LeadDuplicateResolver;
use App\States\Leads\ContactedLead;
use App\Support\Database\DuplicateKeyViolation;
use App\Support\LeadIdentityNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\WebhookServer\WebhookCall;

final class CreateLeadAction
{
    public function __construct(
        private LeadDuplicateResolver $duplicateResolver,
        private LeadIdentityNormalizer $normalizer,
    ) {}

    /**
     * $season lets a caller that already resolved the current season (e.g.
     * CreateLeadWithApplicationAction, so the lead and its application share one resolution)
     * hand it in explicitly. Existing callers that omit it keep the previous behavior of
     * resolving it here. An explicitly supplied season is still untrusted input and is
     * revalidated against the program's type and active state before anything else runs.
     */
    public function execute(
        string $whatsapp,
        string $guardian_name,
        string $student_name,
        int $program_id,
        int $branch_id,
        string $source,
        array $data = [],
        ?string $affiliate_code = null,
        ?Season $season = null,
    ): Lead {
        $whatsapp = normalize_phone_number(
            convert_eastern_arabic_to_arabic($whatsapp),
        );

        $program = Program::findOrFail($program_id);

        $season = $season !== null
            ? $this->assertValidSeasonForProgram($season, $program)
            : Season::current($program->type);

        $affiliate = $this->resolveAffiliate($affiliate_code);
        $motherPhone = $data['mother_phone'] ?? null;
        unset($data['mother_phone']);

        $values = [
            'guardian_name' => $guardian_name,
            'source' => $source,
            'program_type' => $program->type,
            'mother_phone' => $motherPhone,
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
            $existing->fill($this->mergeValues($values, $existing, $student_name));
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
                if (! DuplicateKeyViolation::detect($e)) {
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

                $lead->fill($this->mergeValues($values, $lead, $student_name));
                $lead->save();
                $lead = $lead->fresh();
            }
        }

        $this->dispatchWebhookIfNeeded($lead);

        return $lead;
    }

    /**
     * A duplicate match is a resolution of the *same* lead, not a resubmission — it must not
     * silently overwrite how/where the lead originally arrived. `source`, `affiliate_id`,
     * `affiliate_code_snapshot`, and unrelated `data` keys are preserved from the existing
     * lead; only the display name (already merge-aware) and other non-attribution fields
     * update. Historical `source` can be null on legacy leads, so it is never dereferenced
     * without a null check — the incoming submission's source fills that gap instead of
     * crashing the merge. `data` keys already present on the existing lead win over
     * colliding incoming keys (e.g. `utm_campaign`), so a duplicate resubmission can only add
     * new keys, never overwrite prior attribution.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function mergeValues(array $values, Lead $existing, string $incomingStudentName): array
    {
        $values['student_name'] = $this->normalizer->preferLongerDisplayName(
            $existing->student_name,
            $incomingStudentName,
        );

        $values['source'] = $existing->source?->value ?? $values['source'];
        $values['affiliate_id'] = $existing->affiliate_id;
        $values['affiliate_code_snapshot'] = $existing->affiliate_code_snapshot;
        $values['data'] = ($existing->data ?? []) + ($values['data'] ?? []);

        return $values;
    }

    /**
     * Rejected before any lead lookup or write: a season of the wrong program type or one
     * that is no longer active must never be substituted into the lead (or, transitively,
     * into the application CreateLeadWithApplicationAction derives from it).
     */
    private function assertValidSeasonForProgram(Season $season, Program $program): Season
    {
        if ($season->type !== $program->type || ! $season->is_active) {
            throw new InvalidSeasonForProgramException($season, $program);
        }

        return $season;
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

    /**
     * The status transition is a required database state change and stays inside whatever
     * transaction the caller opened. The outbound webhook call is not — it is registered via
     * `DB::afterCommit()` so a rolled-back lead creation (or one still mid-transaction) can
     * never fire a webhook for data that was never actually persisted. Outside a transaction,
     * `afterCommit()` runs the callback immediately, preserving current behavior for callers
     * that don't wrap this in one.
     */
    private function dispatchWebhookIfNeeded(Lead $lead): void
    {
        if (! $this->shouldDispatchWebhook()) {
            return;
        }

        if ($lead->status->canTransitionTo(ContactedLead::class)) {
            $lead->status->transitionTo(
                ContactedLead::class,
                contactedBy: 'whatsapp_bot',
                contactMethod: LeadContactMethod::Whatsapp,
            );
        }

        DB::afterCommit(function () use ($lead) {
            WebhookCall::create()
                ->url(config('services.webhooks.lead.created_url'))
                ->payload($lead->fresh()->toArray())
                ->useSecret(config('services.webhooks.secret'))
                ->dispatch();
        });
    }

    private function shouldDispatchWebhook(): bool
    {
        return config('services.webhooks.lead.enabled')
            && app()->environment('production');
    }
}
