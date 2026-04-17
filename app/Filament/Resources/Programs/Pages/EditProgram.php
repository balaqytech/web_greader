<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Resources\Programs\ProgramResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProgram extends EditRecord
{
    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['branches'] = $this->record->branches
            ->map(fn ($branch) => [
                'branch_id' => $branch->id,
                'price' => $branch->pivot->price,
            ])
            ->toArray();

        return $data;
    }

    protected function afterSave(): void
    {
        $branches = collect($this->data['branches'] ?? [])
            ->mapWithKeys(fn (array $item) => [
                $item['branch_id'] => ['price' => $item['price']],
            ]);

        $this->record->branches()->sync($branches);
    }
}
