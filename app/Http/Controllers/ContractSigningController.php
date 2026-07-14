<?php

namespace App\Http\Controllers;

use App\Actions\Applications\SignContractOnlineAction;
use App\Models\ApplicationContract;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ContractSigningController extends Controller
{
    public function show(string $token)
    {
        $applicationContract = ApplicationContract::where('token', $token)->first();

        if ($this->isNotSignable($applicationContract)) {
            return view('contract.error', [
                'message' => __('admin.application.contract_invalid_or_expired'),
            ]);
        }

        return view('contract.show', [
            'applicationContract' => $applicationContract,
            'contract' => $this->renderContract($applicationContract),
        ]);
    }

    public function sign(Request $request, string $token, SignContractOnlineAction $action)
    {
        $request->validate([
            'signature' => 'required|string|starts_with:data:image/png;base64,',
        ]);

        $applicationContract = ApplicationContract::where('token', $token)->first();

        // Re-validate signed-off status and expiry on submit, not just on render.
        if ($this->isNotSignable($applicationContract)) {
            return view('contract.error', [
                'message' => __('admin.application.contract_invalid_or_expired'),
            ]);
        }

        try {
            $contract = $this->renderContract($applicationContract);
            $action->execute($applicationContract, $request->input('signature'), $contract);

            return view('contract.success');
        } catch (InvalidArgumentException $e) {
            return view('contract.error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * A contract may only be rendered/signed when it exists, is not already signed off
     * (electronically or via an uploaded copy), and its token is unexpired.
     */
    private function isNotSignable(?ApplicationContract $applicationContract): bool
    {
        return $applicationContract === null
            || $applicationContract->isSignedOff()
            || $applicationContract->isTokenExpired();
    }

    /**
     * Resolve the contract body. The acting guardian (father, mother, or relative) is
     * resolved through the single `guardian_name` domain accessor so GET and POST always
     * render the same signer.
     */
    private function renderContract(ApplicationContract $applicationContract): string
    {
        $application = $applicationContract->application;

        return $this->parseContract($application->program->contract, [
            'program_name' => $application->program->name,
            'parent_name' => $application->guardian_name,
            'student_name' => $application->student_name,
            'enrollment_date' => $application->created_at->format('d/m/Y'),
            'branch_price' => $application->program->branchPrice($application->branch),
        ]);
    }

    private function parseContract(?string $template, array $variables): string
    {
        $template ??= '';

        foreach ($variables as $key => $value) {
            $template = str_replace('$'.$key.'$', (string) $value, $template);
        }

        return $template;
    }
}
