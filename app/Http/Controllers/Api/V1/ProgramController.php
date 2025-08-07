<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Http\Resources\ProgramResource;

class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::open()->get();
        return ProgramResource::collection($programs);
    }
}
