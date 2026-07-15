<?php

namespace Tests\Support;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Filament\Resources\Applications\Pages\CreateApplication;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Program;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared manual-entry fixture construction for ManualEntryTest, ManualEntryAuthorizationTest,
 * and ManualEntryFilamentErrorsTest. Factored out (rather than left as global test-file
 * functions) so each of those files can run standalone in a fresh Artisan process — Pest's
 * global function scope only exists once every discovered test file has been loaded, so a
 * single file executed alone would otherwise fail to find functions defined in a sibling file.
 */
final class ManualEntryFixtures
{
    private static int $phoneCounter = 91000000;

    /**
     * @return array{0: Branch, 1: Program}
     */
    public static function createAvailableBranchAndProgram(): array
    {
        $branch = Branch::factory()->create();
        $program = Program::factory()->create();
        $program->branches()->attach($branch, ['price' => 100]);

        return [$branch, $program];
    }

    /**
     * Deterministic, always-valid Omani local numbers (8 digits, "91" + an incrementing
     * counter). A prior version used fake()->numerify('9#######'), which could occasionally
     * generate a value starting with the bare "968" country-code prefix —
     * normalize_phone_number() rejects that shape (no leading '+' or '0'), causing a rare,
     * non-deterministic test failure. Starting at 91000000 keeps every generated value far
     * outside the 968xxxxx range for any realistic test-suite call volume.
     */
    public static function nextDeterministicPhone(): string
    {
        return (string) self::$phoneCounter++;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function manualEntryData(Branch $branch, Program $program, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $branch->id,
            'program_id' => $program->id,

            'student_name' => 'Student '.fake()->unique()->numerify('####'),
            'student_gender' => Gender::Male,
            'student_birth_date' => '2015-01-01',
            'student_civil_number' => fake()->unique()->numerify('########'),
            'student_state' => 'Muscat',
            'student_governorate' => 'Muscat',
            'student_village' => 'Al Khoud',
            'student_house_number' => '12',
            'student_parents_social_status' => 'married',
            'relationship_with_guardian' => GuardianRelationship::Father,

            'father_name' => 'Father '.fake()->unique()->numerify('####'),
            'father_phone' => self::nextDeterministicPhone(),
            'father_email' => fake()->unique()->safeEmail(),
            'father_id_number' => fake()->unique()->numerify('########'),
            'father_occupation' => 'Engineer',
            'father_work_address' => 'Muscat',
            'father_work_phone' => self::nextDeterministicPhone(),
            'father_is_guardian' => true,

            'mother_name' => 'Mother '.fake()->unique()->numerify('####'),
            'mother_phone' => self::nextDeterministicPhone(),
            'mother_email' => fake()->unique()->safeEmail(),
            'mother_id_number' => fake()->unique()->numerify('########'),
            'mother_occupation' => 'Teacher',
            'mother_work_address' => 'Muscat',
            'mother_work_phone' => self::nextDeterministicPhone(),
            'mother_is_guardian' => false,

            'relative_name' => 'Relative '.fake()->unique()->numerify('####'),
            'relative_phone' => self::nextDeterministicPhone(),
            'relative_email' => fake()->unique()->safeEmail(),
            'relative_id_number' => fake()->unique()->numerify('########'),
            'relative_occupation' => 'Driver',
            'relative_work_address' => 'Muscat',
            'relative_work_phone' => self::nextDeterministicPhone(),
        ], $overrides);
    }

    /**
     * A user genuinely authorized to create applications in $branchId (null => central/
     * branchless, full access).
     */
    public static function authorizedManualEntryUser(?int $branchId = null): User
    {
        $user = User::factory()->create(['branch_id' => $branchId]);

        $permission = Permission::firstOrCreate(['name' => 'Create:Application', 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public static function superAdminUser(?int $branchId = null): User
    {
        $user = User::factory()->create(['branch_id' => $branchId]);

        $permission = Permission::firstOrCreate(['name' => 'Create:Application', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /**
     * The Filament create page exposes record creation only through its protected trait
     * method; this anonymous subclass is the one place that promotes it to public so tests
     * can call it directly.
     */
    public static function manualEntryPage(): CreateApplication
    {
        return new class extends CreateApplication
        {
            public function createRecordFromTrait(array $data): Application
            {
                return $this->handleRecordCreation($data);
            }
        };
    }
}
