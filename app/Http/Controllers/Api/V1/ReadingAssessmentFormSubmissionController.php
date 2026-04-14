<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReadingAssessmentFormSubmissionResource;
use App\Models\ReadingAssessmentFormSubmission;
use Illuminate\Http\Request;

class ReadingAssessmentFormSubmissionController extends Controller
{
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
                $query->where('branch_id', $request->branch_id);
            })
            ->when($request->whatsapp, function ($query) use ($request) {
                $query->where('whatsapp', $request->whatsapp);
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
            'age' => 'required|integer',
            'grade_level' => 'required|string',
            'guardian_name' => 'required|string',
            'whatsapp' => 'required|string',
            'branch_id' => 'required|exists:branches,id',
            'additional_info' => 'nullable|array',
        ]);

        $submission = ReadingAssessmentFormSubmission::create($data);

        return new ReadingAssessmentFormSubmissionResource($submission);
    }

    /**
     * Display the specified resource.
     */
    public function show(ReadingAssessmentFormSubmission $readingAssessmentFormSubmission)
    {
        return new ReadingAssessmentFormSubmissionResource($readingAssessmentFormSubmission);
    }
}
