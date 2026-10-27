<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class MasterAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('flowcore.master_email');
        $password = config('flowcore.master_password');

        if (! $email || ! $password) {
            throw new RuntimeException('Configure FLOWCORE_MASTER_EMAIL e FLOWCORE_MASTER_PASSWORD no .env local.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador Master',
                'password' => $password,
                'role' => 'master',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
