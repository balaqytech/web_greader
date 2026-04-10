<?php

namespace App\Actions\Leads;

use App\Models\Lead;

class CreateLeadAction
{
    public function execute(string $phone, array $data = [], string $source = 'website'): Lead
    {
        $lead = Lead::create([
            'phone' => $this->normalizePhone($phone),
            'data' => $data,
            'source' => $source,
        ]);

        return $lead;
    }

    protected function normalizePhone(string $phone): string
    {
        // remove spaces, dashes, parentheses
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // convert 00 prefix to +
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        // if starts with 0 → assume local and prepend Oman country code (+968)
        if (str_starts_with($phone, '0')) {
            $phone = '+968' . substr($phone, 1);
        }

        // if no + at all → force it (dangerous but better than garbage)
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        // final validation (basic E.164: + followed by 8–15 digits)
        if (!preg_match('/^\+[1-9]\d{7,14}$/', $phone)) {
            throw new \InvalidArgumentException('Invalid phone number format.');
        }

        return $phone;
    }
}
