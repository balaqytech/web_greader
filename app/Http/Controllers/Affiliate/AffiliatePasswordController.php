<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AffiliatePasswordController extends Controller
{
    public function edit(): View
    {
        return view('affiliate.password', [
            'affiliate' => Auth::guard('affiliate')->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $affiliate->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('affiliate.password.alerts.current_password_incorrect'),
            ]);
        }

        $affiliate->update([
            'password' => $validated['password'],
        ]);

        return redirect()->route('affiliate.password.edit')
            ->with('status', __('affiliate.password.alerts.updated'));
    }
}
