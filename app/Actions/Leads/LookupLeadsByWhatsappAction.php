<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Models\Lead;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Collection;

/**
 * The exact-whatsapp lookup behind `GET /leads`. The branchless Fasih service account has no
 * branch to scope to, so this bypasses {@see BranchScope} — and only that scope — inside a
 * single authorized query rather than through any cross-branch Shield grant. Matching is on
 * the fully normalized number so a chatbot need not know the stored format; a partial or
 * unnormalized number can never fan out into a broad listing.
 */
final class LookupLeadsByWhatsappAction
{
    /**
     * @return Collection<int, Lead>
     */
    public function execute(string $whatsapp): Collection
    {
        try {
            $normalized = normalize_phone_number(
                convert_eastern_arabic_to_arabic($whatsapp),
            );
        } catch (\InvalidArgumentException) {
            // An un-normalizable number cannot match any stored lead — answer with an empty
            // result rather than leaking a 500.
            return new Collection;
        }

        return Lead::withoutGlobalScope(BranchScope::class)
            ->with(['branch', 'program'])
            ->where('whatsapp', $normalized)
            ->orderByDesc('created_at')
            ->get();
    }
}
