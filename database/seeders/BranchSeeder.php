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
            [
                'name' => 'المعبيلة الثامنة',
                'address' => 'مسقط',
                'phone' => '97408000',
                'mobile' => '79399302',
            ],
            [
                'name' => 'الموالح الجنوبية',
                'address' => 'مسقط',
                'phone' => '79399297',
                'mobile' => '79377837',
            ],
            [
                'name' => 'العامرات',
                'address' => 'مسقط',
                'phone' => '79399303',
                'mobile' => null,
            ],
            [
                'name' => 'بوشر',
                'address' => 'مسقط',
                'phone' => '97719795',
                'mobile' => '75656071',
            ],
            [
                'name' => 'الخوض',
                'address' => 'مسقط',
                'phone' => '94809448',
                'mobile' => null,
            ],
            [
                'name' => 'بهلا',
                'address' => 'الداخلية',
                'phone' => '79399308',
                'mobile' => null,
            ],
            [
                'name' => 'بدبد',
                'address' => 'الداخلية',
                'phone' => '79399306',
                'mobile' => null,
            ],
            [
                'name' => 'نزوى',
                'address' => 'الداخلية',
                'phone' => '97796160',
                'mobile' => null,
            ],
            [
                'name' => 'بركا',
                'address' => 'الباطنة',
                'phone' => '79399304',
                'mobile' => null,
            ],
            [
                'name' => 'السويق',
                'address' => 'الباطنة',
                'phone' => '79908065',
                'mobile' => null,
            ],
            [
                'name' => 'صحار',
                'address' => 'الباطنة',
                'phone' => '99860508',
                'mobile' => null,
            ],
            [
                'name' => 'إبراء',
                'address' => 'شمال الشرقية',
                'phone' => '94647965',
                'mobile' => null,
            ],
            [
                'name' => 'ينقل',
                'address' => 'الظاهرة',
                'phone' => '79132153',
                'mobile' => null,
            ],
            [
                'name' => 'البريمي',
                'address' => 'البريمي',
                'phone' => '79119436',
                'mobile' => null,
            ],
        ];
    }
}
