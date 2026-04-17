<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReadingAssessment\CreateSubmission;
use App\Enums\Source;
use App\Exceptions\DuplicateSubmissionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReadingAssessmentFormSubmissionResource;
use App\Models\ReadingAssessmentFormSubmission;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReadingAssessmentFormSubmissionController extends Controller
{
    public function  __construct(
        private readonly CreateSubmission $createSubmission
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'whatsapp' => 'nullable|string',
        ]);

        $submissions = ReadingAssessmentFormSubmission::with('branch')
            ->when($request->branch_id, function ($query) use ($request) {
                $query->where('branch_id', 'like', '%' . $request->branch_id . '%');
            })
            ->when($request->whatsapp, function ($query) use ($request) {
                $query->where('whatsapp', 'like', '%' . $request->whatsapp . '%');
            })
            ->paginate(15);

        return ReadingAssessmentFormSubmissionResource::collection($submissions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_name' => 'required|string',
            'age' => 'required|integer|min:4|max:13',
            'grade_level' => 'required|string',
            'guardian_name' => 'required|string',
            'whatsapp' => 'required|string',
            'branch_id' => 'required|exists:branches,id',
            'source' => ['required', Rule::enum(Source::class)],
            'additional_info' => 'nullable|array',
        ]);

        $submission = $this->createSubmission->execute($data);

        return new ReadingAssessmentFormSubmissionResource($submission);
    }

    /**
     * Display the specified resource.
     */
    public function show(ReadingAssessmentFormSubmission $submission)
    {
        return new ReadingAssessmentFormSubmissionResource($submission->load('branch'));
    }
}
