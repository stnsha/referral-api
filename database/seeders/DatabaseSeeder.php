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
        User::create([
            'name' => 'superadmin',
            'email' => 'anasuharosli.alphac@gmail.com',
            'password' => bcrypt('fzElFOAz1RU1g8a')
        ]);

        $this->call(BusinessUnitSeeder::class);
        $this->call(BusinessUnitFormSeeder::class);
    }
}
