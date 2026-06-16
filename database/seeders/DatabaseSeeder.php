<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles + perms + demo users (if you already added them)
        if (class_exists(\Database\Seeders\SetupRolesAndPermissionsSeeder::class)) {
            $this->call(SetupRolesAndPermissionsSeeder::class);
        }
        if (class_exists(\Database\Seeders\DemoUsersSeeder::class)) {
            $this->call(DemoUsersSeeder::class);
        }

        $this->call(RoomSeeder::class);
        $this->call(TaskSeeder::class);

        // Bulk graph data (100+ props, sessions, etc.)
        $this->call(BulkDemoDataSeeder::class);
    }
}
