<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin
        User::firstOrCreate(
            ['email' => 'admin@sekolah.com'],
            [
                'name'              => 'Admin TU',
                'password'          => bcrypt('password'),
                'role'              => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // 2. Tata Usaha
        User::firstOrCreate(
            ['email' => 'tu@sekolah.com'],
            [
                'name'              => 'Staff TU',
                'password'          => bcrypt('password'),
                'role'              => 'tu',
                'email_verified_at' => now(),
            ]
        );

        // 3. Staf
        User::firstOrCreate(
            ['email' => 'staf@sekolah.com'],
            [
                'name'              => 'Staff Sekolah',
                'password'          => bcrypt('password'),
                'role'              => 'staf',
                'email_verified_at' => now(),
            ]
        );

        // 4. Kepala Sekolah
        User::firstOrCreate(
            ['email' => 'kepsek@sekolah.com'],
            [
                'name'              => 'Kepala Sekolah',
                'password'          => bcrypt('password'),
                'role'              => 'kepala_sekolah',
                'email_verified_at' => now(),
            ]
        );
    }
}
