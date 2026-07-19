<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leads\CreateLeadAction;
use App\Actions\Leads\LookupLeadsByWhatsappAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LookupLeadsRequest;
use App\Http\Requests\Api\V1\StoreLeadRequest;
use App\Http\Resources\Api\V1\LeadSummaryResource;
use App\Models\Lead;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(
        private CreateLeadAction $createLeadAction,
    ) {}

    /**
     * Exact-whatsapp lookup for the Fasih service account. Returns the minimal
     * {@see LeadSummaryResource} projection only — never guardian PII, phones, or the `data`
     * bag — and can never be coerced into browsing every lead.
     */
    public function index(LookupLeadsRequest $request, LookupLeadsByWhatsappAction $lookup)
    {
        $leads = $lookup->execute($request->validated('whatsapp'));

        return LeadSummaryResource::collection($leads);
    }

    public function counts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'status' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
        ]);

        $baseQuery = $this->leadCountsQuery($validated);

        $branchCounts = (clone $baseQuery)
            ->join('branches', 'branches.id', '=', 'leads.branch_id')
            ->select('leads.branch_id', 'branches.name as branch_name')
            ->selectRaw('count(*) as leads_count')
            ->groupBy('leads.branch_id', 'branches.name')
            ->orderBy('leads.branch_id')
            ->get()
            ->map(fn ($row): array => [
                'branch_id' => (int) $row->branch_id,
                'branch_name' => $row->branch_name,
                'leads_count' => (int) $row->leads_count,
            ]);

        $programCounts = (clone $baseQuery)
            ->join('branches', 'branches.id', '=', 'leads.branch_id')
            ->join('programs', 'programs.id', '=', 'leads.program_id')
            ->select(
                'leads.branch_id',
                'branches.name as branch_name',
                'leads.program_id',
                'programs.name as program_name',
            )
            ->selectRaw('count(*) as leads_count')
            ->groupBy('leads.branch_id', 'branches.name', 'leads.program_id', 'programs.name')
            ->orderBy('leads.branch_id')
            ->orderBy('leads.program_id')
            ->get()
            ->map(fn ($row): array => [
                'branch_id' => (int) $row->branch_id,
                'branch_name' => $row->branch_name,
                'program_id' => (int) $row->program_id,
                'program_name' => $row->program_name,
                'leads_count' => (int) $row->leads_count,
            ]);

        return response()->json([
            'data' => [
                'total_leads' => (clone $baseQuery)->count(),
                'branches' => $branchCounts,
                'programs_by_branch' => $programCounts,
            ],
        ]);
    }

    public function store(StoreLeadRequest $request)
    {
        $validated = $request->validated();

        $data = $request->input('data') ?? $request->except([
            'whatsapp',
            'program_id',
            'branch_id',
            'guardian_name',
            'student_name',
            'source',
            'affiliate_code',
            'mother_phone',
        ]);
        $data = is_array($data) ? $data : [];

        if ($request->has('mother_phone')) {
            $data['mother_phone'] = $validated['mother_phone'] ?? null;
        }

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

        return new LeadSummaryResource($lead->loadMissing(['branch', 'program']));
    }

    /**
     * The branchless Fasih service account has no branch to scope to, so cross-branch count
     * aggregates bypass {@see BranchScope} — and only that scope — inside this authorized query
     * rather than through any cross-branch Shield grant. Callers still narrow the result with
     * the validated `branch_id`/`program_id`/… filters.
     *
     * @param  array<string, mixed>  $filters
     */
    private function leadCountsQuery(array $filters): Builder
    {
        return Lead::query()
            ->withoutGlobalScope(BranchScope::class)
            ->when(isset($filters['branch_id']), fn (Builder $query) => $query->where('leads.branch_id', $filters['branch_id']))
            ->when(isset($filters['program_id']), fn (Builder $query) => $query->where('leads.program_id', $filters['program_id']))
            ->when(isset($filters['season_id']), fn (Builder $query) => $query->where('leads.season_id', $filters['season_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('leads.status', $filters['status']))
            ->when(isset($filters['source']), fn (Builder $query) => $query->where('leads.source', $filters['source']))
            ->when(isset($filters['created_from']), fn (Builder $query) => $query->whereDate('leads.created_at', '>=', $filters['created_from']))
            ->when(isset($filters['created_to']), fn (Builder $query) => $query->whereDate('leads.created_at', '<=', $filters['created_to']));
    }
}
