<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Alpro Audiology',     'staff_department_id' => 1],
            ['name' => 'Alpro Baby',   'staff_department_id' => 21],
            ['name' => 'Alpro Clinic',      'staff_department_id' => 2],
            ['name' => 'Alpro Optisaver',     'staff_department_id' => 35],
            ['name' => 'Alpro Pharmacy',  'staff_department_id' => 1],
            ['name' => 'Alpro Physio',       'staff_department_id' => 20],
            ['name' => 'Alpro Sugi',       'staff_department_id' => 1],
        ];

        foreach ($units as $unit) {
            BusinessUnit::firstOrCreate(
                [
                    'name' => $unit['name'],
                ],
                [
                    'staff_department_id' => $unit['staff_department_id'],
                    'is_active' => 1
                ]
            );
        }
    }
}
