<?php

namespace App\Http\Controllers;

use App\Actions\Applications\SignContractOnlineAction;
use App\Models\Application;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ContractSigningController extends Controller
{
    public function show(string $token)
    {
        $application = Application::where('contract_token', $token)->first();

        if (! $application || ! $application->hasValidContractToken()) {
            return view('contract.error', [
                'message' => __('admin.application.contract_invalid_or_expired'),
            ]);
        }

        return view('contract.show', compact('application'));
    }

    public function sign(Request $request, string $token, SignContractOnlineAction $action)
    {
        $request->validate([
            'signature' => 'required|string|starts_with:data:image/png;base64,',
        ]);

        $application = Application::where('contract_token', $token)->first();

        if (! $application || ! $application->hasValidContractToken()) {
            return view('contract.error', [
                'message' => __('admin.application.contract_invalid_or_expired'),
            ]);
        }

        try {
            $action->execute($application, $request->input('signature'));

            return view('contract.success');
        } catch (InvalidArgumentException $e) {
            return view('contract.error', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
