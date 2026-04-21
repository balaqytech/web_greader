<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateProfileController extends Controller
{
    public function edit(): View
    {
        return view('affiliate.profile', [
            'affiliate' => Auth::guard('affiliate')->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

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

        $validated['whatsapp'] = normalize_phone_number(
            convert_eastern_arabic_to_arabic($validated['whatsapp']),
        );

        $affiliate->update($validated);

        return redirect()->route('affiliate.profile.edit')
            ->with('status', __('affiliate.profile.alerts.updated'));
    }
}
