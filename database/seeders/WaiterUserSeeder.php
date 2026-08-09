<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WaiterUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'garcom@restaurante.com'],
            [
                'name' => 'Garçom',
                'password' => Hash::make('password'),
                'role' => 'waiter',
                'email_verified_at' => now(),
            ]
        );

        User::where('email', 'garcom@restaurante.com')->update(['role' => 'waiter']);

        User::where('email', 'admin@restaurante.com')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }
}
