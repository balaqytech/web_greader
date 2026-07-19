<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReadingAssessment\CreateSubmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexReadingAssessmentRequest;
use App\Http\Requests\Api\V1\StoreReadingAssessmentRequest;
use App\Http\Resources\ReadingAssessmentFormSubmissionResource;
use App\Models\ReadingAssessmentFormSubmission;

class ReadingAssessmentFormSubmissionController extends Controller
{
    public function __construct(
        private readonly CreateSubmission $createSubmission
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexReadingAssessmentRequest $request)
    {
        $submissions = ReadingAssessmentFormSubmission::with('branch')
            ->when($request->filled('branch_id'), function ($query) use ($request) {
                $query->where('branch_id', $request->integer('branch_id'));
            })
            ->when($request->filled('whatsapp'), function ($query) use ($request) {
                $query->where('whatsapp', 'like', '%'.$request->string('whatsapp').'%');
            })
            ->paginate(15);

        return ReadingAssessmentFormSubmissionResource::collection($submissions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReadingAssessmentRequest $request)
    {
        $submission = $this->createSubmission->execute($request->validated());

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
