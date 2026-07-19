<?php

use App\Actions\ReadingAssessment\CreateSubmission;
use App\Enums\Source;
use App\Models\Branch;
use App\Models\ReadingAssessmentFormSubmission;
use Illuminate\Database\QueryException;

function assessmentPayload(Branch $branch, array $overrides = []): array
{
    return array_merge([
        'student_name' => 'Sara Ahmed',
        'age' => 8,
        'grade_level' => 'Grade 3',
        'guardian_name' => 'Ahmed',
        'whatsapp' => '099123456',
        'branch_id' => $branch->id,
        'source' => Source::WEBSITE->value,
    ], $overrides);
}

it('dedupes on the normalized composite identity and merges instead of duplicating', function () {
    $branch = Branch::factory()->create();
    $action = app(CreateSubmission::class);

    $first = $action->execute(assessmentPayload($branch, ['age' => 8]));

    // Same whatsapp (unnormalized form) + student + branch, different age → merges into the row.
    $second = $action->execute(assessmentPayload($branch, ['whatsapp' => '٠٩٩١٢٣٤٥٦', 'age' => 9]));

    expect($second->getKey())->toBe($first->getKey())
        ->and(ReadingAssessmentFormSubmission::withoutGlobalScopes()->count())->toBe(1)
        ->and($second->fresh()->age)->toBe(9);
});

it('normalizes the whatsapp number before persisting', function () {
    $branch = Branch::factory()->create();

    $submission = app(CreateSubmission::class)->execute(assessmentPayload($branch));

    expect($submission->whatsapp)->toBe(normalize_phone_number('099123456'));
});

it('propagates an unexpected database error rather than returning an uninitialized result', function () {
    $branch = Branch::factory()->create();

    // `age` is a NOT NULL column with no default; omitting it forces a non-duplicate
    // QueryException that must surface, never be swallowed into a null return.
    expect(fn () => app(CreateSubmission::class)->execute([
        'student_name' => 'No Age',
        'grade_level' => 'Grade 1',
        'guardian_name' => 'Guardian',
        'whatsapp' => '099123456',
        'branch_id' => $branch->id,
        'source' => Source::WEBSITE->value,
    ]))->toThrow(QueryException::class);
});
