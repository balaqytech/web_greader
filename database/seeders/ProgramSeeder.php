<?php

namespace Database\Seeders;

use App\Enums\ProgramType;
use App\Models\Branch;
use App\Models\Program;
use App\Models\Season;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::all()->keyBy('name');

        $early_education_programs = [
            // التعليم المبكر
            'الروضة' => ProgramType::Academic,
            'التمهيدي' => ProgramType::Academic,
        ];

        $primary_programs = [
            // الحلقة الأولى
            'الصف الأول' => 1,
            'الصف الثاني' => 2,
            'الصف الثالث' => 3,
            'الصف الرابع' => 4,
        ];

        $summer_programs = [
            // البرامج الصيفية
            'برنامج سنا الصيفي' => ProgramType::Summer,
            'برنامج الحافظ الماهر' => ProgramType::Summer,
        ];

        // Mapping branches to what they offer
        $branch_offerings = [
            'العامرات' => ['early' => true, 'primary' => [1, 2, 3, 4]],
            'العذيبة' => ['early' => true, 'primary' => [1, 2, 3, 4]],
            'المعبيلة الثامنة' => ['early' => true, 'primary' => [1, 2, 3, 4]],
            'الخوض' => ['early' => false, 'primary' => [1, 2, 3, 4]],
            'بوشر' => ['early' => true, 'primary' => [1, 2]],
            'الموالح' => ['early' => true, 'primary' => []],
            'المعبيلة الجنوبية' => ['early' => true, 'primary' => []],
            'بركاء' => ['early' => true, 'primary' => []],
            'السويق' => ['early' => true, 'primary' => []],
            'صحار' => ['early' => true, 'primary' => []],
            'البريمي' => ['early' => true, 'primary' => []],
            'ينقل' => ['early' => true, 'primary' => []],
            'بدبد' => ['early' => true, 'primary' => []],
            'بهلاء' => ['early' => true, 'primary' => []],
            'نزوى' => ['early' => true, 'primary' => []],
            'إبراء' => ['early' => true, 'primary' => [1, 2]],
            'صور' => ['early' => true, 'primary' => []],
        ];

        // 1. Seed Early Education Programs
        foreach ($early_education_programs as $name => $type) {
            $program = Program::firstOrCreate(['name' => $name], ['type' => $type]);
            $pivotData = [];

            foreach ($branch_offerings as $branchName => $offerings) {
                if ($offerings['early']) {
                    $branch = $branches->get($branchName);
                    if ($branch) {
                        // الرسوم المعتمدة في فروع محافظة مسقط للتعليم المبكر = 1599
                        // الرسوم المعتمدة في فروع بقية المحافظات للتعليم المبكر = 1399
                        $price = $branch->governorate === 'مسقط' ? 1599 : 1399;
                        $pivotData[$branch->id] = ['price' => $price];
                    }
                }
            }
            $program->branches()->sync($pivotData);
        }

        // 2. Seed Primary Programs (الحلقة الأولى)
        foreach ($primary_programs as $name => $grade) {
            $program = Program::firstOrCreate(['name' => $name], ['type' => ProgramType::Academic]);
            $pivotData = [];

            foreach ($branch_offerings as $branchName => $offerings) {
                if (in_array($grade, $offerings['primary'])) {
                    $branch = $branches->get($branchName);
                    if ($branch) {
                        // الرسوم المعتمدة للحلقة الأولى = 1799
                        $pivotData[$branch->id] = ['price' => 1799];
                    }
                }
            }
            $program->branches()->sync($pivotData);
        }

        // 3. Seed Summer Programs (to all branches as before)
        foreach ($summer_programs as $name => $type) {
            $program = Program::firstOrCreate(['name' => $name], ['type' => $type]);
            $pivotData = [];

            foreach ($branches as $branch) {
                $pivotData[$branch->id] = ['price' => 70]; // Defaulting to 70 as before
            }
            $program->branches()->sync($pivotData);
        }
    }
}
