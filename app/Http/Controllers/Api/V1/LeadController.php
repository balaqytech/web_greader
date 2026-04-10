<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leads\CreateLeadAction;
use App\Enums\LeadSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class LeadController extends Controller
{
    public function __construct(
        private CreateLeadAction $createLeadAction
    ) {}

    public function index(Request $request): ResourceCollection
    {
        $leads = Lead::filter($request->all())
            ->latest()
            ->paginate(10);

        return LeadResource::collection($leads);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:15', 'min:8'],
            'source' => ['required', Rule::enum(LeadSource::class)],
        ]);

        $lead = $this->createLeadAction->execute(
            $validated['phone'],
            $request->except(['phone', 'source']),
            $validated['source']
        );

        return response()->json([
            'message' => __('alerts.lead_created_successfully'),
            'lead' => new LeadResource($lead),
        ]);
    }
}
