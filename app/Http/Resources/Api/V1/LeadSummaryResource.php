<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The deliberately minimal projection of a lead returned to the Fasih service account. It is
 * an allowlist, not a redaction: only these fields are ever emitted. Internal IDs, guardian
 * details, phone numbers, the arbitrary `data` bag, and affiliate contact data are all
 * excluded so a whatsapp lookup can confirm a lead's existence and status without leaking PII.
 *
 * @mixin Lead
 */
class LeadSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ref_no' => $this->ref_no,
            'student_name' => $this->student_name,
            'status' => $this->status::$name,
            'status_label' => $this->status::getLabel(),
            'branch_name' => $this->branch?->name,
            'program_name' => $this->program?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
