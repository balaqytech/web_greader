<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::insert($this->branches());
    }

    private function branches(): array
    {
        return [
            // محافظة مسقط
            [
                'name' => 'العامرات',
                'governorate' => 'مسقط',
                'phone' => '79399303',
                'mobile' => null,
            ],
            [
                'name' => 'العذيبة',
                'governorate' => 'مسقط',
                'phone' => null,
                'mobile' => null,
            ],
            [
                'name' => 'المعبيلة الثامنة',
                'governorate' => 'مسقط',
                'phone' => '97408000',
                'mobile' => '79399302',
            ],
            [
                'name' => 'الخوض',
                'governorate' => 'مسقط',
                'phone' => '94809448',
                'mobile' => null,
            ],
            [
                'name' => 'بوشر',
                'governorate' => 'مسقط',
                'phone' => '97719795',
                'mobile' => '75656071',
            ],
            [
                'name' => 'الموالح',
                'governorate' => 'مسقط',
                'phone' => '79399297',
                'mobile' => '79377837',
            ],
            [
                'name' => 'المعبيلة الجنوبية',
                'governorate' => 'مسقط',
                'phone' => null,
                'mobile' => null,
            ],
            // محافظة الباطنة (شمال وجنوب)
            [
                'name' => 'بركاء',
                'governorate' => 'الباطنة',
                'phone' => '79399304',
                'mobile' => null,
            ],
            [
                'name' => 'السويق',
                'governorate' => 'الباطنة',
                'phone' => '79908065',
                'mobile' => null,
            ],
            [
                'name' => 'صحار',
                'governorate' => 'الباطنة',
                'phone' => '99860508',
                'mobile' => null,
            ],
            // محافظة البريمي
            [
                'name' => 'البريمي',
                'governorate' => 'البريمي',
                'phone' => '79119436',
                'mobile' => null,
            ],
            // محافظة الظاهرة
            [
                'name' => 'ينقل',
                'governorate' => 'الظاهرة',
                'phone' => '79132153',
                'mobile' => null,
            ],
            // محافظة الداخلية
            [
                'name' => 'بدبد',
                'governorate' => 'الداخلية',
                'phone' => '79399306',
                'mobile' => null,
            ],
            [
                'name' => 'بهلاء',
                'governorate' => 'الداخلية',
                'phone' => '79399308',
                'mobile' => null,
            ],
            [
                'name' => 'نزوى',
                'governorate' => 'الداخلية',
                'phone' => '97796160',
                'mobile' => null,
            ],
            // محافظات الشرقية (شمال وجنوب)
            [
                'name' => 'إبراء',
                'governorate' => 'شمال الشرقية',
                'phone' => '94647965',
                'mobile' => null,
            ],
            [
                'name' => 'صور',
                'governorate' => 'جنوب الشرقية',
                'phone' => null,
                'mobile' => null,
            ],
        ];
    }
}
