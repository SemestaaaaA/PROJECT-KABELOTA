<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('kabelota:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("Akun {$email} tidak ditemukan. Minta orangnya daftar dulu.");

        return 1;
    }

    $user->forceFill(['role' => 'admin', 'email_verified_at' => $user->email_verified_at ?? now()])->save();
    $this->info("{$email} sekarang admin. Panel: ".url('/admin'));
})->purpose('Jadikan akun yang sudah terdaftar sebagai admin (untuk anggota QA)');

Artisan::command('kabelota:reset-demo {--force : Lewati konfirmasi}', function () {
    if (! config('kabelota.demo_mode')) {
        $this->error('Hanya bisa dijalankan saat KABELOTA_DEMO=true.');

        return 1;
    }

    if (! $this->option('force') && ! $this->confirm('Semua data dan file unggahan akan dihapus lalu diisi data contoh. Lanjut?')) {
        return 0;
    }

    // Uploaded files live in these folders on the public and local disks.
    foreach (['public', 'local'] as $disk) {
        foreach (Storage::disk($disk)->directories() as $dir) {
            if (! str_starts_with($dir, '.')) {
                Storage::disk($disk)->deleteDirectory($dir);
            }
        }
    }

    $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
    $this->info('Data demo sudah bersih.');
})->purpose('Kosongkan data dan unggahan, lalu isi ulang data contoh');
