<?php

namespace Database\Factories;

use App\Enums\AffiliateCategory;
use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Affiliate>
 */
class AffiliateFactory extends Factory
{
    protected $model = Affiliate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'code' => strtoupper(fake()->unique()->lexify('???')).fake()->unique()->numberBetween(100, 999),
            'category' => fake()->randomElement(AffiliateCategory::cases()),
            'whatsapp' => fake()->unique()->numerify('966#########'),
            'password' => 'password',
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
