<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Actions\ProgramEnrollment\Register;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, Register $register)
    {
        $request->merge([
            'source' => \App\Enums\EnrollmentSource::WHATSAPP->value,
        ]);
        return $register->execute($request->validated());
    }
}
