<?php

namespace App\Actions\ReadingAssessment;

use App\Exceptions\DuplicateSubmissionException;
use App\Models\ReadingAssessmentFormSubmission;
use App\Models\Scopes\BranchScope;
use App\Support\Database\DuplicateKeyViolation;
use App\Support\LeadIdentityNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;

class CreateSubmission
{
    public function __construct(
        private LeadIdentityNormalizer $normalizer,
    ) {}

    /**
     * Create (or dedupe against) a reading-assessment submission.
     *
     * Deduplication is keyed on the normalized composite identity — whatsapp, student name,
     * and branch — the same tuple the unique constraint enforces. Because the branchless Fasih
     * service account is scoped to no branch, the identity lookup bypasses {@see BranchScope}
     * (and only that scope) so it can find an existing submission for any branch and merge into
     * it instead of colliding with the unique index.
     *
     * An unexpected database error is never swallowed: only a genuine duplicate-key violation
     * is recovered (by re-reading the winning row). Any other {@see QueryException} propagates,
     * so the method can never return an uninitialized result.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): ReadingAssessmentFormSubmission
    {
        $data['whatsapp'] = $this->formatWhatsapp($data['whatsapp']);
        $data['student_name'] = $this->normalizer->normalizeName($data['student_name']);

        $identity = Arr::only($data, ['whatsapp', 'student_name', 'branch_id']);

        if (($existing = $this->findByIdentity($identity)) !== null) {
            return $this->merge($existing, $data);
        }

        try {
            return ReadingAssessmentFormSubmission::create($data);
        } catch (QueryException $e) {
            if (! DuplicateKeyViolation::detect($e)) {
                throw $e;
            }

            $existing = $this->findByIdentity($identity);

            if ($existing === null) {
                throw new DuplicateSubmissionException;
            }

            return $this->merge($existing, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function findByIdentity(array $identity): ?ReadingAssessmentFormSubmission
    {
        return ReadingAssessmentFormSubmission::withoutGlobalScope(BranchScope::class)
            ->where('whatsapp', $identity['whatsapp'])
            ->where('student_name', $identity['student_name'])
            ->where('branch_id', $identity['branch_id'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function merge(ReadingAssessmentFormSubmission $existing, array $data): ReadingAssessmentFormSubmission
    {
        $existing->fill(Arr::except($data, ['whatsapp', 'student_name', 'branch_id']));
        $existing->save();

        return $existing;
    }

    private function formatWhatsapp(string $whatsapp): string
    {
        $whatsapp = convert_eastern_arabic_to_arabic($whatsapp);
        $whatsapp = normalize_phone_number($whatsapp);

        return $whatsapp;
    }
}
