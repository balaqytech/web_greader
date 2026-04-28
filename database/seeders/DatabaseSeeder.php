<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'as3ad',
            'email' => 'as3ad.moh@gmail.com',
            'password' => '123123123',
        ]);

        $this->call([
            ShieldSeeder::class,
            ProgramSeeder::class,
        ]);
    }
}
