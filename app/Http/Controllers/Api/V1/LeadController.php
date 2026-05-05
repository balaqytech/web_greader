<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leads\CreateLeadAction;
use App\Actions\Leads\TransitionLeadStateAction;
use App\Enums\LeadContactMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\States\Leads\LeadState;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\ModelStates\Validation\ValidStateRule;

class LeadController extends Controller
{
    public function __construct(
        private CreateLeadAction $createLeadAction,
        private TransitionLeadStateAction $transitionLeadStateAction,
    ) {}

    public function index(Request $request)
    {
        $allowedFilters = ['whatsapp', 'program_id', 'branch_id', 'status', 'source'];

        $filters = $request->only($allowedFilters);

        $leads = Lead::query()
            ->with(['branch', 'program', 'season', 'affiliate'])
            ->filter($filters);

        $leads->when(
            $request->has('created_from') && $request->has('created_to'),
            fn($query) => $query->whereBetween('created_at', [$request->created_from, $request->created_to])
        )->when(
            $request->has('created_from'),
            fn($query) => $query->whereDate('created_at', '>=', $request->created_from)
        )->when(
            $request->has('created_to'),
            fn($query) => $query->whereDate('created_at', '<=', $request->created_to)
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