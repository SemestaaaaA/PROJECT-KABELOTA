<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Production: one admin account, nothing else. Safe to run more than once. */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('kabelota.admin_password');

        if (blank($password) || strlen($password) < 12) {
            throw new \RuntimeException('Isi KABELOTA_ADMIN_PASSWORD (minimal 12 karakter) di .env sebelum seeding produksi.');
        }

        User::firstOrCreate(
            ['email' => config('kabelota.admin_email')],
            ['name' => 'Admin Kabelota', 'role' => 'admin', 'password' => $password, 'email_verified_at' => now()],
        );
    }
}
