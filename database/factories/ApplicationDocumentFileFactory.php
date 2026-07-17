<?php

namespace Database\Factories;

use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocumentFile>
 */
class ApplicationDocumentFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_document_id' => ApplicationDocument::factory(),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'original_name' => fake()->lexify('????????').'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(100000, 5000000),
            'uploaded_at' => now(),
        ];
    }
}
