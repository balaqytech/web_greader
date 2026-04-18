<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AffiliateLoginController extends Controller
{
    public function create(): View
    {
        return view('affiliate.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'whatsapp' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials['whatsapp'] = normalize_phone_number(
            convert_eastern_arabic_to_arabic($credentials['whatsapp']),
        );

        if (! Auth::guard('affiliate')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'whatsapp' => __('affiliate.auth.alerts.login.wrong_credentials'),
            ])->onlyInput('whatsapp');
        }

        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        if (! $affiliate->isVerified()) {
            Auth::guard('affiliate')->logout();

            return back()->withErrors([
                'whatsapp' => __('affiliate.auth.alerts.login.wait_for_approval'),
            ])->onlyInput('whatsapp');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('affiliate.dashboard'));
    }
}
