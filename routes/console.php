<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
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

Artisan::command('kabelota:close-expired', function () {
    $closed = \App\Models\JobPosting::where('status', 'aktif')->whereDate('closes_at', '<', today())->update(['status' => 'ditutup']);
    $this->info("{$closed} lowongan ditutup karena masa tayang habis.");
})->purpose('Tutup lowongan yang masa tayangnya sudah habis');

Artisan::command('kabelota:remind-skk', function () {
    $due = \App\Models\Certification::query()
        ->whereNull('reminded_at')
        ->whereBetween('expires_at', [today(), today()->addDays(30)])
        ->with('talent.user')
        ->get();

    foreach ($due as $certification) {
        $certification->talent?->user?->notify(new \App\Notifications\SkkExpiring($certification));
        $certification->update(['reminded_at' => now()]);
    }

    $this->info("{$due->count()} pengingat SKK dikirim.");
})->purpose('Email talenta yang SKK-nya habis dalam 30 hari');

Schedule::command('kabelota:close-expired')->dailyAt('00:10');
Schedule::command('kabelota:remind-skk')->dailyAt('08:00');

// Database dump + uploaded files, kept on the 'backups' disk (see config/backup.php).
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30');
Schedule::command('backup:monitor')->dailyAt('09:00');

Artisan::command('kabelota:seed-if-empty', function () {
    if (User::exists()) {
        $this->info('Database sudah berisi data, seeding dilewati.');

        return 0;
    }

    $this->call('db:seed', ['--force' => true]);
})->purpose('Isi database hanya saat pertama kali deploy (masih kosong)');
