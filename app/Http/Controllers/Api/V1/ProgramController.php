<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::active()
            ->when($request->has('type'), function ($query) use ($request) {
                $query->where('type', $request->type);
            })
            ->when($request->has('branch_id'), function ($query) use ($request) {
                $query->whereHas('branches', function ($query) use ($request) {
                    $query->where('branch_id', $request->branch_id);
                });
            })
            ->get();

        return ProgramResource::collection($programs);
    }

    public function show(Program $program)
    {
        return new ProgramResource($program);
    }
}
