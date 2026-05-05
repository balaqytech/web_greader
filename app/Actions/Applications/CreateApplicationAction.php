<?php

namespace App\Actions\Applications;

use App\DTOs\Application\CreateApplicationDTO;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

class CreateApplicationAction
{
    public function execute(CreateApplicationDTO $dto): Application
    {
        return DB::transaction(function () use ($dto) {
            /** @var Application $application */
            $application = Application::create($dto->toArray());

            return $application;
        });
    }
}
