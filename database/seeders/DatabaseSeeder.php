<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(RolesAndPermissionsSeeder::class);

        // Create an admin user
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@itsmint.io',
        ]);
        $admin->assignRole('admin');

        // Create a regular user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@itsmint.com',
        ]);
        $user->assignRole('user');
    }
}
