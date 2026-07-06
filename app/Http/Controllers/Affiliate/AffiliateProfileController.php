<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AffiliateProfileController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $request->merge([
            'whatsapp' => $this->normalizeWhatsappForValidation((string) $request->input('whatsapp', '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[0-9]+$/',
                Rule::unique('affiliates', 'whatsapp')->ignore($affiliate->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('affiliates', 'email')->ignore($affiliate->id),
            ],
        ]);

        $affiliate->update($validated);

        return redirect()->route('affiliate.profile.edit')
            ->with('status', __('affiliate.profile.alerts.updated'));
    }

    private function normalizeWhatsappForValidation(string $whatsapp): string
    {
        $whatsapp = convert_eastern_arabic_to_arabic($whatsapp);
        $whatsapp = preg_replace('/[^0-9+]/', '', $whatsapp) ?? $whatsapp;

        if (str_starts_with($whatsapp, '968')) {
            $whatsapp = '+'.$whatsapp;
        }

        try {
            return normalize_phone_number($whatsapp);
        } catch (InvalidArgumentException) {
            return $whatsapp;
        }
    }
}
