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
        $allowed = ['whatsapp', 'program_id', 'branch_id', 'status', 'source'];

        $leads = Lead::query();

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $allowed)) {
                $leads = $leads->where($key, 'like', "%{$value}%");
            }
        }

        $leads = $leads->get();

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

    public function transition(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'target_state' => ['required', new ValidStateRule(LeadState::class)],
            'contacted_by' => ['required', 'string'],
            'contact_method' => ['required', Rule::enum(LeadContactMethod::class)],
            'notes' => 'nullable|string',
            'follow_up_at' => 'nullable|date',
        ]);

        $contactMethod = LeadContactMethod::from($validated['contact_method']);

        $lead = match ($validated['target_state']) {
            'contacted' => $this->transitionLeadStateAction->toContacted(
                lead: $lead,
                contactedBy: $validated['contacted_by'],
                contactMethod: $contactMethod,
                notes: $validated['notes'] ?? null,
                followUpAt: $validated['follow_up_at'] ?? null,
            ),
            'interested' => $this->transitionLeadStateAction->toInterested(
                lead: $lead,
                contactedBy: $validated['contacted_by'],
                contactMethod: $contactMethod,
                notes: $validated['notes'] ?? null,
            ),
            'not_interested' => $this->transitionLeadStateAction->toNotInterested(
                lead: $lead,
                contactedBy: $validated['contacted_by'],
                contactMethod: $contactMethod,
                notes: $validated['notes'] ?? null,
            ),
            'no_response' => $this->transitionLeadStateAction->toNoResponse(
                lead: $lead,
                contactedBy: $validated['contacted_by'],
                contactMethod: $contactMethod,
                notes: $validated['notes'] ?? null,
                followUpAt: $validated['follow_up_at'] ?? null,
            ),
        };

        return new LeadResource($lead);
    }
}
