<?php

namespace App\Http\Controllers;

use App\Actions\Applications\RenderApplicationContractAction;
use App\Actions\Applications\SignContractOnlineAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Exceptions\StaleApplicationStateException;
use App\Models\ApplicationContract;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ContractSigningController extends Controller
{
    public function show(string $token, RenderApplicationContractAction $render)
    {
        $applicationContract = ApplicationContract::with('application')->where('token', $token)->first();

        if ($this->isNotSignable($applicationContract)) {
            return view('contract.error', [
                'message' => __('admin.application.contract_invalid_or_expired'),
            ]);
        }

        return view('contract.show', [
            'applicationContract' => $applicationContract,
            'contract' => $render->execute($applicationContract->application),
        ]);
    }

    public function sign(Request $request, string $token, SignContractOnlineAction $action)
    {
        $request->validate([
            'signature' => 'required|string|starts_with:data:image/png;base64,',
        ]);

        $applicationContract = ApplicationContract::with('application')->where('token', $token)->first();

        // Re-validate signed-off status and expiry on submit, not just on render.
        if ($this->isNotSignable($applicationContract)) {
            return view('contract.error', [
                'message' => __('admin.application.contract_invalid_or_expired'),
            ]);
        }

        try {
            // The contract body is deliberately NOT rendered here: it is rendered by the
            // action itself, after locking, from the locked (current) application data — not
            // from this pre-lock read, which could be stale by the time the lock is acquired.
            $action->execute($applicationContract, $token, $request->input('signature'));

            return view('contract.success');
        } catch (InvalidArgumentException $e) {
            return view('contract.error', [
                'message' => $e->getMessage(),
            ]);
        } catch (StaleApplicationStateException|ApplicationIncompleteException) {
            // A concurrent request already changed the underlying state (e.g. a second
            // signer, a staff reopen, or a cancellation) between render and submit. This is
            // an expected domain outcome, not a server error, so it must not surface as a 500.
            return view('contract.error', [
                'message' => __('admin.application.contract_invalid_or_expired'),
            ]);
        }
    }

    /**
     * A contract may only be rendered/signed per the single authoritative rule on the
     * application (App\Models\Application::hasSignableContract()).
     */
    private function isNotSignable(?ApplicationContract $applicationContract): bool
    {
        return $applicationContract === null
            || $applicationContract->application === null
            || ! $applicationContract->application->hasSignableContract($applicationContract);
    }
}
