<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string',
        ]);

        $branches = Branch::active()
            ->when($request->name, function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->name}%");
            })
            ->get();

        return BranchResource::collection($branches);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $branch = Branch::active()->findOrFail($id);

        return new BranchResource($branch);
    }
}
