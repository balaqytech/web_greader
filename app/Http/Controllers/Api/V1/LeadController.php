<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leads\CreateLeadAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

        $dataFilters = $request->input('data', []);
        foreach ($dataFilters as $key => $value) {
            $filters["data.{$key}"] = $value;
        }

        $leads = Lead::query()
            ->with(['branch', 'program', 'season', 'affiliate'])
            ->filter($filters);

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

        if ($request->filled('created_from')) {
            $leads->where('created_at', '>=', $request->created_from);
        }

        if ($request->filled('created_to')) {
            $leads->where('created_at', '<=', $request->created_to);
        }

        $leads = $leads
            ->orderBy('created_at', 'asc')
            ->paginate(15)
            ->appends($request->query());

        return LeadResource::collection($leads);
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
            'mother_phone' => 'nullable|string',
        ]);

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
            $data['mother_phone'] = $validated['mother_phone'];
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

        return new LeadResource($lead);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function leadCountsQuery(array $filters): Builder
    {
        return Lead::query()
            ->when(isset($filters['branch_id']), fn (Builder $query) => $query->where('leads.branch_id', $filters['branch_id']))
            ->when(isset($filters['program_id']), fn (Builder $query) => $query->where('leads.program_id', $filters['program_id']))
            ->when(isset($filters['season_id']), fn (Builder $query) => $query->where('leads.season_id', $filters['season_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('leads.status', $filters['status']))
            ->when(isset($filters['source']), fn (Builder $query) => $query->where('leads.source', $filters['source']))
            ->when(isset($filters['created_from']), fn (Builder $query) => $query->whereDate('leads.created_at', '>=', $filters['created_from']))
            ->when(isset($filters['created_to']), fn (Builder $query) => $query->whereDate('leads.created_at', '<=', $filters['created_to']));
    }
}
