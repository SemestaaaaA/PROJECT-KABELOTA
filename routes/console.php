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

Artisan::command('kabelota:preflight', function () {
    $checks = [];
    $add = function (string $level, string $item, string $fix = '') use (&$checks) {
        $checks[] = [$level, $item, $fix];
    };
    $placeholder = fn (?string $v, array $bad) => blank($v) || \Illuminate\Support\Str::contains((string) $v, $bad);

    app()->isProduction() ? $add('ok', 'APP_ENV=production') : $add('fail', 'APP_ENV='.app()->environment(), 'Set APP_ENV=production');
    config('app.debug') ? $add('fail', 'APP_DEBUG menyala', 'Set APP_DEBUG=false') : $add('ok', 'APP_DEBUG=false');
    filled(config('app.key')) ? $add('ok', 'APP_KEY terisi') : $add('fail', 'APP_KEY kosong', 'php artisan key:generate --show');
    str_starts_with((string) config('app.url'), 'https://') ? $add('ok', 'APP_URL memakai https') : $add('fail', 'APP_URL bukan https: '.config('app.url'), 'Isi domain asli, mis. https://kabelota.id');
    config('kabelota.demo_mode') ? $add('fail', 'Mode demo menyala', 'Set KABELOTA_DEMO=false') : $add('ok', 'Mode demo mati (2FA admin wajib)');
    config('session.secure') ? $add('ok', 'Cookie sesi hanya lewat https') : $add('warn', 'SESSION_SECURE_COOKIE belum true');
    config('queue.default') === 'sync' ? $add('warn', 'QUEUE_CONNECTION=sync, email memperlambat halaman') : $add('ok', 'Queue: '.config('queue.default'));

    $mailer = config('mail.default');
    in_array($mailer, ['log', 'array'], true) ? $add('fail', "MAIL_MAILER=$mailer (email tidak terkirim)", 'Pakai brevo atau smtp') : $add('ok', "Mailer: $mailer");
    if ($mailer === 'brevo' && blank(config('services.brevo.key'))) {
        $add('fail', 'BREVO_API_KEY kosong');
    }
    $placeholder(config('mail.from.address'), ['example.com', '.test', 'hello@']) ? $add('fail', 'MAIL_FROM_ADDRESS belum diisi alamat asli') : $add('ok', 'Pengirim email: '.config('mail.from.address'));
    config('kabelota.ops_email') === 'admin@kabelota.id' ? $add('warn', 'KABELOTA_OPS_EMAIL masih default', 'Isi email yang benar-benar dibaca admin') : $add('ok', 'Alert ke '.config('kabelota.ops_email'));

    $placeholder(config('kabelota.bank.holder'), ['placeholder']) || str_contains((string) config('kabelota.bank.number'), '0000000')
        ? $add('fail', 'Rekening pembayaran masih placeholder', 'Isi KABELOTA_BANK_NAME/NUMBER/HOLDER')
        : $add('ok', 'Rekening: '.config('kabelota.bank.name'));
    str_contains((string) config('kabelota.contact.whatsapp'), '0000-0000') ? $add('fail', 'WhatsApp kontak masih placeholder', 'Isi KABELOTA_CONTACT_WHATSAPP') : $add('ok', 'WhatsApp kontak terisi');

    foreach (['privacy', 'terms'] as $page) {
        $found = preg_match_all('/\[[^\]]{3,60}\]/', file_get_contents(resource_path("views/legal/$page.blade.php")));
        $found ? $add('fail', "Halaman legal $page masih punya $found isian [kurung siku]", 'Lengkapi bersama klien + ahli hukum') : $add('ok', "Halaman legal $page lengkap");
    }
    str_contains(file_get_contents(resource_path('views/components/legal-page.blade.php')), 'draft-note')
        ? $add('fail', 'Banner "Draf" masih tampil di halaman legal', 'Hapus div.draft-note di components/legal-page.blade.php setelah ditinjau ahli hukum')
        : $add('ok', 'Halaman legal tanpa banner draf');
    str_contains(file_get_contents(resource_path('views/about.blade.php')), 'draft-note')
        ? $add('warn', 'Narasi Tentang Kami masih ditandai draf', 'Konfirmasi ke HMTS, lalu hapus p.draft-note di about.blade.php')
        : $add('ok', 'Narasi Tentang Kami final');
    str_contains((string) config('kabelota.photos.hero.src'), 'hero-jakarta') ? $add('warn', 'Foto hero masih foto stok', 'Ganti di config/kabelota.php > photos') : $add('ok', 'Foto hero sudah diganti');

    is_link(public_path('storage')) ? $add('ok', 'storage:link ada') : $add('fail', 'public/storage belum ada', 'php artisan storage:link');
    config('backup.backup.destination.disks') === ['backups']
        ? $add('warn', 'Backup hanya di server yang sama', 'Arahkan BACKUP_DISK ke S3/R2 atau unduh rutin')
        : $add('ok', 'Backup ke: '.implode(', ', config('backup.backup.destination.disks')));

    $admins = User::where('role', 'admin')->get();
    $admins->isEmpty() ? $add('fail', 'Belum ada akun admin', 'php artisan db:seed --force (ProductionSeeder)') : $add('ok', $admins->count().' akun admin');
    $noMfa = $admins->whereNull('app_authentication_secret')->count();
    $noMfa ? $add('warn', "$noMfa admin belum memasang 2FA", 'Akan diminta saat login pertama') : $add('ok', 'Semua admin memakai 2FA');

    $icons = ['ok' => '<info>OK  </info>', 'warn' => '<comment>CEK </comment>', 'fail' => '<error>GAGAL</error>'];
    foreach ($checks as [$level, $item, $fix]) {
        $this->line($icons[$level].' '.$item.($fix ? "  → $fix" : ''));
    }

    $fails = collect($checks)->where(0, 'fail')->count();
    $this->newLine();
    $fails ? $this->error("$fails hal harus dibereskan sebelum launch.") : $this->info('Siap launch. Periksa juga baris CEK di atas.');

    return $fails ? 1 : 0;
})->purpose('Cek setelan sebelum launch produksi');
