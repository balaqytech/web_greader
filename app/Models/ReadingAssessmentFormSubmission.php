<?php

namespace App\Models;

use App\Enums\Source;
use App\Enums\SubmissionStatus;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_name', 'age', 'grade_level', 'guardian_name', 'whatsapp', 'branch_id', 'status', 'source', 'additional_info'])]
class ReadingAssessmentFormSubmission extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'source' => Source::class,
            'additional_info' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
