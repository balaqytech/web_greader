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

it('lists and shows submissions across branches for the branchless service account', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $first = app(CreateSubmission::class)->execute(assessmentPayload($branchA));
    $second = app(CreateSubmission::class)->execute(assessmentPayload($branchB, [
        'student_name' => 'Mona Ali',
        'whatsapp' => '099765432',
    ]));
    [, $token] = fasihServiceToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/reading-assessment-form-submissions')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/reading-assessment-form-submissions?whatsapp=099123456')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $first->id);

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/reading-assessment-form-submissions/'.$second->id)
        ->assertOk()
        ->assertJsonPath('data.id', $second->id);
});

it('rejects invalid assessment phone input instead of throwing or persisting it', function () {
    $branch = Branch::factory()->create();
    [, $token] = fasihServiceToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', 'invalid-assessment-phone')
        ->postJson('/api/v1/reading-assessment-form-submissions', assessmentPayload($branch, [
            'whatsapp' => 'not-phone',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('whatsapp');

    expect(ReadingAssessmentFormSubmission::withoutGlobalScopes()->count())->toBe(0);
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
