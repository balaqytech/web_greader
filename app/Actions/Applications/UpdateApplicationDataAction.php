<?php

namespace App\Actions\Applications;

use App\DTOs\Application\UpdateApplicationDataDTO;
use App\Models\Application;

final class UpdateApplicationDataAction
{
    /**
     * Update the application's registration data fields.
     */
    public function execute(Application $application, UpdateApplicationDataDTO $dto): Application
    {
        $application->fill($dto->toArray());
        $application->save();

        return $application->fresh();
    }
}
