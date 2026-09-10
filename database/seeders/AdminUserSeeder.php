<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Change this password immediately after first login.
        $user = User::updateOrCreate(
            ['email' => 'admin@mucahost.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('change-me-now'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Fortify's own registration flow gives every new user a personal
        // team via this same action — do the same here, since the seeder
        // bypasses registration and the dashboard route requires one.
        if (! $user->personalTeam()) {
            app(CreateTeam::class)->handle($user, "{$user->name}'s Team", true);
        }
    }
}
