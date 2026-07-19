<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Applications\MatchApplicationForGuardianAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StatusCheckRequest;
use App\Http\Resources\Api\V1\ApplicationStatusResource;
use Illuminate\Http\JsonResponse;

class ApplicationStatusController extends Controller
{
    public function __construct(
        private readonly MatchApplicationForGuardianAction $matchApplication,
    ) {}

    /**
     * Verified status lookup: a guardian proves ownership with the application reference and
     * their phone, and receives the minimal status projection. Every reference/phone mismatch
     * answers with the same generic 404 so the endpoint reveals nothing about which value was
     * wrong — or whether the reference exists at all.
     */
    public function statusCheck(StatusCheckRequest $request): JsonResponse
    {
        $application = $this->matchApplication->execute(
            $request->validated('application_reference'),
            $request->validated('guardian_phone'),
        );

        if ($application === null) {
            return response()->json(['message' => __('alerts.api.application_not_found')], 404);
        }

        return (new ApplicationStatusResource($application))->response();
    }
}
