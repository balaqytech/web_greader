<?php

namespace App\Traits;

use App\Support\LeadIdentityNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasNormalizedStudentName
{
    protected static function bootHasNormalizedStudentName(): void
    {
        static::saving(function (self $lead): void {
            if (! $lead->isDirty('student_name') && $lead->student_name_normalized && $lead->identity_fingerprint) {
                return;
            }

            if (! $lead->student_name) {
                return;
            }

            $normalizer = app(LeadIdentityNormalizer::class);
            $lead->student_name_normalized = $normalizer->normalizeName($lead->student_name);

            if ($lead->whatsapp && $lead->program_id && $lead->season_id) {
                $lead->identity_fingerprint = $normalizer->fingerprint(
                    $lead->whatsapp,
                    $lead->program_id,
                    $lead->season_id,
                    $lead->branch_id,
                    $lead->student_name,
                );
            }
        });
    }

    protected function studentName(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => trim($value),
        );
    }
}
