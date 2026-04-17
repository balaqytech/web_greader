<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Resources\Programs\ProgramResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProgram extends CreateRecord
{
    protected static string $resource = ProgramResource::class;

    protected function afterCreate(): void
    {
        $branches = collect($this->data['branches'] ?? [])
            ->mapWithKeys(fn (array $item) => [
                $item['branch_id'] => ['price' => $item['price']],
            ]);

        $this->record->branches()->sync($branches);
    }
}
