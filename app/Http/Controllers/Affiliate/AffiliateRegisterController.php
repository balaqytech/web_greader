<?php

namespace App\Http\Controllers\Affiliate;

use App\Enums\Source;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AffiliateRegisterController extends Controller
{
    public function create(): View
    {
        return view('affiliate.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:20', 'unique:affiliates,whatsapp'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        Affiliate::create([
            ...$validated,
            'creation_source' => Source::WEBSITE,
        ]);

        return redirect()->route('affiliate.login')
            ->with('status', __('affiliate.auth.alerts.register.success'));
    }
}
