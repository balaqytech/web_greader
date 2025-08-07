<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Http\Resources\BranchResource;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::active()->get();
        return BranchResource::collection($branches);
    }
}
