<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('ADMIN_EMAIL', 'admin@fincompta.dz'));

        $password = env('ADMIN_PASSWORD');
        if (! is_string($password) || $password === '') {
            if (app()->environment('local')) {
                $password = 'password';
            } else {
                $this->command->warn('ADMIN_PASSWORD is empty; skipping admin user seed (set ADMIN_EMAIL and ADMIN_PASSWORD in .env).');

                return;
            }
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) env('ADMIN_NAME', 'Administrateur')),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(['admin']);

        $this->command->info('Admin user ready: '.$email);
    }
}
