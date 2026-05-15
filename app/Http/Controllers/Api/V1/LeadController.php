<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leads\CreateLeadAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(
        private CreateLeadAction $createLeadAction,
    ) {}

    public function index(Request $request)
    {
        $allowedFilters = ['whatsapp', 'program_id', 'branch_id', 'status', 'source'];

        $filters = $request->only($allowedFilters);

        // Merge data.* JSON filters from request: ?data[mother_phone]=123
        $dataFilters = $request->input('data', []);
        foreach ($dataFilters as $key => $value) {
            $filters["data.{$key}"] = $value;
        }

        $leads = Lead::query()
            ->with(['branch', 'program', 'season', 'affiliate'])
            ->filter($filters);

        // Full-text-style search: ?search=term&search_fields[]=data.mother_phone
        $leads->when(
            $request->filled('search'),
            function ($query) use ($request) {
                $jsonKeys = collect($request->input('search_fields', []))
                    ->filter(fn ($f) => str_starts_with($f, 'data.'))
                    ->map(fn ($f) => substr($f, 5))
                    ->values()
                    ->all();

                $query->search($request->search, $jsonKeys);
            }
        );

        $leads->when(
            $request->has('created_from') && $request->has('created_to'),
            fn ($query) => $query->whereBetween('created_at', [$request->created_from, $request->created_to])
        )->when(
            $request->has('created_from'),
            fn ($query) => $query->whereDate('created_at', '>=', $request->created_from)
        )->when(
            $request->has('created_to'),
            fn ($query) => $query->whereDate('created_at', '<=', $request->created_to)
        );

        $leads = $leads->paginate(15);

        return LeadResource::collection($leads);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'whatsapp' => 'required|min:8|max:16',
            'program_id' => 'required|exists:programs,id',
            'branch_id' => 'required|exists:branches,id',
            'guardian_name' => 'required',
            'student_name' => 'required',
            'source' => 'required',
            'affiliate_code' => 'nullable|string',
        ]);

        $data = $request->input('data') ?? $request->except([
            'whatsapp',
            'program_id',
            'branch_id',
            'guardian_name',
            'student_name',
            'source',
            'affiliate_code',
        ]);

        $lead = $this->createLeadAction->execute(
            $validated['whatsapp'],
            $validated['guardian_name'],
            $validated['student_name'],
            $validated['program_id'],
            $validated['branch_id'],
            $validated['source'],
            $data,
            $validated['affiliate_code'] ?? null,
        );

        return new LeadResource($lead);
    }
}
