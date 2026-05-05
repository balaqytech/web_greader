<?php

namespace App\Http\Controllers;

use App\Actions\Applications\SignContractOnlineAction;
use App\Models\Application;
use App\Models\ApplicationContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ContractSigningController extends Controller
{
    public function show(string $token)
    {
        $applicationContract = ApplicationContract::where('token', $token)->first();

        if (! $applicationContract || $applicationContract->isTokenExpired()) {
            return view('contract.error', [
                'message' => __('admin.application.contract_invalid_or_expired'),
            ]);
        }

        $variables = [
            'program_name' => $applicationContract->application->program->name,
            // 'parent_name' => $applicationContract->application->student->parentAccount->name,
            'student_name' => $applicationContract->application->applicationStudent->name,
            'enrollment_date' => $applicationContract->application->created_at->format('d/m/Y'),
            'final_price' => $applicationContract->application->program->price,
        ];

        $contract = $this->parseContract($applicationContract->application->program->contract, $variables);

        return view('contract.show', compact('applicationContract', 'contract'));
    }

    public function sign(Request $request, string $token, SignContractOnlineAction $action)
    {
        $request->validate([
            'signature' => 'required|string|starts_with:data:image/png;base64,',
        ]);

        $applicationContract = ApplicationContract::where('token', $token)->first();

        if (! $applicationContract || $applicationContract->isTokenExpired()) {
            return view('contract.error', [
                'message' => __('admin.application.contract_invalid_or_expired'),
            ]);
        }

        try {
            $variables = [
                'program_name' => $applicationContract->application->program->name,
                // 'parent_name' => $applicationContract->application->student->parentAccount->name,
                'student_name' => $applicationContract->application->applicationStudent->name,
                'enrollment_date' => $applicationContract->application->created_at->format('d/m/Y'),
                'final_price' => $applicationContract->application->program->price,
            ];

            $contract = $this->parseContract($applicationContract->application->program->contract, $variables);
            $action->execute($applicationContract, $request->input('signature'), $contract);

            return view('contract.success');
        } catch (InvalidArgumentException $e) {
            return view('contract.error', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function parseContract($template, $variables)
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('$' . $key . '$', $value, $template);
        }
        return $template;
    }

    private function storeSignature($signature)
    {
        // Decode the base64 string
        $image = str_replace('data:image/png;base64,', '', $signature);
        $image = str_replace(' ', '+', $image);
        $image = base64_decode($image);

        // Generate a unique filename
        $filename = 'contracts/signatures/signature_' . time() . '.png';

        // Store the image in the public disk
        Storage::disk('public')->put($filename, $image);

        return Storage::url($filename);
    }
}
