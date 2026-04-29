<?php

namespace App\Actions\Applications;

use App\DTOs\Application\CreateApplicationDTO;
use App\Models\Application;

class CreateApplicationAction
{
    public function execute(CreateApplicationDTO $dto): Application
    {
        $application = Application::create($dto->toArray());
        return $application;
    }
}
