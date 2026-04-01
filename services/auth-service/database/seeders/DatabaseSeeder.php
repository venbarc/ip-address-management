<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'super admin',
                'role' => UserRole::SuperAdmin,
                'password' => 'password123',
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'simple user',
                'role' => UserRole::User,
                'password' => 'password123',
            ]
        );
    }
}
