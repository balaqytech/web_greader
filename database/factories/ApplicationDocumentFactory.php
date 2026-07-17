<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'branch_id' => fn (array $attributes): int => (int) Application::withoutGlobalScopes()
                ->whereKey($attributes['application_id'])
                ->valueOrFail('branch_id'),
            'type' => DocumentType::BirthCertificate,
            'status' => 'missing',
            'is_required' => true,
            'requirement_group' => null,
        ];
    }
}
