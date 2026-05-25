<?php

namespace App\Http\Controllers\Affiliate;

use App\Enums\Source;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AffiliateRegisterController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->merge([
            'whatsapp' => $this->normalizeWhatsappForValidation((string) $request->input('whatsapp', '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]+$/', 'unique:affiliates,whatsapp'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        Affiliate::create([
            ...$validated,
            'creation_source' => Source::WEBSITE,
        ]);

        return redirect()->route('affiliate.login')
            ->with('status', __('affiliate.auth.alerts.register.success'));
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
