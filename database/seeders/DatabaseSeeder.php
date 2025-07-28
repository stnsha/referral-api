<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            [

                'email' => 'anasuharosli.alphac@gmail.com'
            ],
            [
                'name' => 'superadmin',
                'password' => bcrypt('fzElFOAz1RU1g8a')
            ]
        );

        $this->call(BusinessUnitSeeder::class);
        $this->call(BusinessUnitFormSeeder::class);
        $this->call(ExternalRefereeSeeder::class);
        // $this->call(ReferralSeeder::class);
    }
}
