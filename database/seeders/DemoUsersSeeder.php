<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Use env to override default passwords in production
        $defaultPass = env('DEMO_USER_PASSWORD', 'password');

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Mr. Admin', 'password' => Hash::make($defaultPass), 'email_verified_at' => now()]
        );
        $admin->syncRoles(['admin', 'owner', 'housekeeper']);

        $owner = User::firstOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Mr. Owner', 'password' => Hash::make($defaultPass), 'email_verified_at' => now()]
        );
        $owner->syncRoles(['owner']);

        $hk = User::firstOrCreate(
            ['email' => 'housekeeper@example.com'],
            ['name' => 'Mr. Housekeeper', 'password' => Hash::make($defaultPass), 'email_verified_at' => now()]
        );
        $hk->syncRoles(['housekeeper']);

        // Your personal account (Permanent)
        $user = User::firstOrCreate(
            ['email' => 'shashanklnaik03@gmail.com'],
            ['name' => 'Shashank Naik', 'password' => Hash::make($defaultPass), 'email_verified_at' => now()]
        );
        $user->syncRoles(['admin', 'owner']);
    }
}
