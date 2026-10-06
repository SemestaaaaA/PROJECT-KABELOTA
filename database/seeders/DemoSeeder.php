<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\JobApplication;
use App\Models\RecruitmentOffer;
use App\Models\JobPosting;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Demo data: 5 verified companies, 50 talents, jobs, offers and applications. Never run in production. */
class DemoSeeder extends Seeder
{
    /** Projects drawn from typical Central Sulawesi public works. All demo data. */
    private const PROJECTS = [
        ['Preservasi Jalan Ruas Tawaeli - Toboli', 'Parigi Moutong', 'BPJN Sulawesi Tengah', 'Transportasi'],
        ['Rekonstruksi Jembatan Palu IV', 'Palu', 'BPJN Sulawesi Tengah', 'Struktur'],
        ['Rehabilitasi Daerah Irigasi Gumbasa', 'Sigi', 'BWS Sulawesi III', 'Keairan'],
        ['Pembangunan Tanggul Pantai Teluk Palu', 'Palu', 'BWS Sulawesi III', 'Keairan'],
        ['Pembangunan Gedung Rawat Inap RSUD Undata', 'Palu', 'Dinas Kesehatan Provinsi', 'Struktur'],
        ['Pelebaran Jalan Trans Sulawesi Poso - Tentena', 'Poso', 'BPJN Sulawesi Tengah', 'Transportasi'],
        ['Hunian Tetap Pascabencana Tondo', 'Palu', 'Balai Prasarana Permukiman', 'Manajemen Konstruksi'],
        ['Penanganan Longsor Ruas Kebun Kopi', 'Parigi Moutong', 'BPJN Sulawesi Tengah', 'Geoteknik'],
        ['Pembangunan Gedung Sekolah SMKN 3 Palu', 'Palu', 'Dinas Pendidikan Provinsi', 'Struktur'],
        ['Peningkatan Jalan Akses Kawasan Industri Morowali', 'Morowali', 'Dinas Bina Marga Provinsi', 'Transportasi'],
        ['Normalisasi Sungai Palu', 'Palu', 'BWS Sulawesi III', 'Keairan'],
        ['Perbaikan Tanah Kawasan Likuifaksi Balaroa', 'Palu', 'Balai Prasarana Permukiman', 'Geoteknik'],
    ];

    /** Position => typical duties, so project histories don't read identical. */
    private const POSITIONS = [
        'Site Engineer' => 'Mengatur metode kerja harian, memeriksa shop drawing, dan memimpin rapat koordinasi mingguan dengan direksi teknis.',
        'Quantity Surveyor' => 'Menghitung volume pekerjaan, menyiapkan backup data MC bulanan, dan mengecek tagihan subkontraktor.',
        'Pengawas Lapangan' => 'Mengawasi pekerjaan sesuai spesifikasi teknis, mencatat laporan harian, dan menandatangani berita acara opname.',
        'Drafter' => 'Membuat gambar kerja dan as-built drawing dengan AutoCAD, serta merevisi gambar sesuai kondisi lapangan.',
        'Quality Engineer' => 'Menjadwalkan uji material (slump, kuat tekan, CBR), mengarsipkan hasil laboratorium, dan menutup NCR.',
        'Pelaksana' => 'Mengatur tenaga kerja dan alat berat, mengejar progres sesuai kurva S, dan melapor ke site manager.',
        'Ahli K3' => 'Menyusun RKK, memimpin safety induction dan toolbox meeting, serta menginvestigasi insiden di lapangan.',
        'Team Leader' => 'Memimpin tim konsultan pengawas, menyetujui laporan bulanan, dan mewakili konsultan di rapat dengan PPK.',
    ];

    private const CONTRACTORS = ['PT Lembah Palu Konstruksi', 'CV Tadulako Karya', 'PT Sigi Bangun Mandiri', 'PT Teluk Palu Engineering', 'CV Donggala Jaya'];

    public function run(): void
    {
        fake()->seed(2026);

        $companies = collect([
            ['CV Lembah Palu Konsultan', 'konsultan', 'Palu'],
            ['PT Sigi Bangun Mandiri', 'kontraktor', 'Sigi'],
            ['PT Tanjung Karang Bangun', 'kontraktor', 'Palu'],
            ['CV Poso Rekayasa', 'konsultan', 'Poso'],
            ['PT Teluk Palu Engineering', 'kontraktor', 'Palu'],
        ])->map(fn ($c) => Company::create([
            'name' => $c[0], 'type' => $c[1], 'city' => $c[2], 'status' => 'terverifikasi', 'verified_at' => now(),
            'nib' => fake()->numerify('#############'), 'contact_name' => fake()->randomElement(['Rina Lamba', 'Arman Saleh', 'Dewi Pakaya']),
            'contact_phone' => '0812'.fake()->numerify('########'),
        ]));

        // Local accounts. Change the passwords in .env before sharing a public demo.
        User::create([
            'name' => 'Admin Kabelota', 'email' => 'admin@kabelota.test', 'role' => 'admin',
            'password' => $this->password('admin_password'), 'email_verified_at' => now(),
        ]);
        $hrd = User::create([
            'name' => 'HRD Demo', 'email' => 'hrd@demo.kabelota.test', 'role' => 'perusahaan',
            'password' => $this->password('demo_password'), 'email_verified_at' => now(),
        ]);
        $companies->first()->update(['user_id' => $hrd->id, 'contact_name' => 'Rina Lamba']);

        Talent::factory(40)->create()->each(fn (Talent $t) => $this->fillAlumni($t));
        Talent::factory(10)->student()->create()->each(fn (Talent $t) => $this->fillStudent($t));

        $jobs = [
            [0, 'Site Engineer Jalan, Paket Preservasi Ruas Tawaeli - Toboli', 'tenaga_ahli', 'Transportasi', 7, 4, 8, 'Parigi Moutong', 12],
            [3, 'Ahli Sumber Daya Air untuk Tender Irigasi Gumbasa', 'tenaga_ahli', 'Keairan', 8, 8, 10, 'Sigi', 4],
            [2, 'Drafter Struktur (AutoCAD, Revit)', 'reguler', 'Struktur', null, 1, 6, 'Palu', 7],
            [4, 'Quantity Surveyor Proyek Gedung', 'reguler', 'Manajemen Konstruksi', null, 2, 12, 'Palu', 9],
            [2, 'Drafter Proyek Smelter (penempatan Morowali Utara dan Kendari)', 'reguler', 'Struktur', null, 2, 12, 'Luar Sulawesi Tengah', 11],
            [1, 'Asisten Pengawas Gedung Sekolah', 'magang', 'Struktur', null, null, 3, 'Sigi', 5],
            [0, 'Magang Survei Lalu Lintas Kota Palu', 'magang', 'Transportasi', null, null, 2, 'Palu', 3],
            [4, 'Ahli K3 Konstruksi Proyek Tanggul Pantai', 'tenaga_ahli', 'Manajemen Konstruksi', 7, 5, 9, 'Palu', 6],
        ];
        foreach ($jobs as $i => [$co, $title, $pkg, $conc, $jen, $exp, $dur, $loc, $apps]) {
            JobPosting::create([
                'company_id' => $companies[$co]->id,
                'title' => $title,
                'package' => $pkg,
                'concentration' => $conc,
                'min_jenjang' => $jen,
                'min_experience' => $exp,
                'duration_months' => $dur,
                'location' => $loc,
                'description' => 'Dibutuhkan untuk paket pekerjaan tahun anggaran 2027. Penempatan di lokasi proyek, mess dan transport lokal disediakan.',
                'closes_at' => today()->addDays(10 + $i * 4),
            ]);
        }

        // A few applications and offers so the HRD demo dashboards are not empty.
        $alumni = Talent::where('type', 'alumni')->where('availability', 'tersedia')->inRandomOrder()->take(8)->get();
        foreach (JobPosting::where('company_id', $companies->first()->id)->get() as $job) {
            foreach ($alumni->random(min(3, $alumni->count())) as $i => $talent) {
                JobApplication::create([
                    'job_posting_id' => $job->id, 'talent_id' => $talent->id,
                    'status' => ['baru', 'ditinjau', 'baru'][$i % 3],
                    'message' => 'Saya berdomisili di '.$talent->city.' dan siap ditempatkan di lokasi proyek.',
                ]);
            }
        }
        foreach ($alumni->take(2) as $i => $talent) {
            RecruitmentOffer::create([
                'talent_id' => $talent->id, 'company_id' => $companies->first()->id, 'company_name' => $companies->first()->name,
                'contact_name' => 'Rina Lamba', 'contact_email' => 'hrd@demo.kabelota.test',
                'position' => $i ? 'Quantity Surveyor Proyek Gedung' : 'Site Engineer Jalan',
                'duration' => '8 bulan', 'message' => 'Kami sedang menyiapkan dokumen penawaran dan membutuhkan personel sesuai SKK Anda.',
                'status' => $i ? 'menunggu' : 'diterima', 'responded_at' => $i ? null : now()->subDay(),
            ]);
        }

        $this->spreadTimeline($companies);
    }

    /**
     * Spread the sample data over the last 12 weeks and add paid postings from earlier months,
     * so the admin dashboard charts show a realistic history instead of one spike today.
     */
    private function spreadTimeline($companies): void
    {
        $at = fn (int $maxDays, int $minDays = 0) => now()->subDays(fake()->numberBetween($minDays, $maxDays))->setTime(fake()->numberBetween(7, 21), fake()->numberBetween(0, 59));

        // Sign-ups grow over time: more recent weeks get more talents.
        Talent::query()->each(function (Talent $t) use ($at) {
            $days = (int) round(84 * (1 - sqrt(fake()->randomFloat(4, 0, 1))));
            DB::table('talents')->where('id', $t->id)->update(['created_at' => $at($days, max(0, $days - 6)), 'updated_at' => now()]);
        });

        $companies->each(function (Company $c, int $i) use ($at) {
            $created = $at(84 - $i * 10, 70 - $i * 10);
            DB::table('companies')->where('id', $c->id)->update(['created_at' => $created, 'verified_at' => $created->copy()->addDays(2)]);
        });

        // Open postings were approved in the last three weeks.
        JobPosting::query()->each(fn (JobPosting $j) => DB::table('job_postings')->where('id', $j->id)
            ->update(['created_at' => $created = $at(21, 3), 'approved_at' => $created->copy()->addDay()]));

        // Paid postings from earlier months, already closed: history for the revenue chart.
        foreach (range(1, 5) as $monthsAgo) {
            foreach (range(1, fake()->numberBetween(1, 3)) as $n) {
                $package = fake()->randomElement(['magang', 'reguler', 'reguler', 'tenaga_ahli']);
                $approved = now()->subMonths($monthsAgo)->startOfMonth()->addDays(fake()->numberBetween(0, 25));
                JobPosting::create([
                    'company_id' => $companies->random()->id, 'title' => 'Lowongan selesai #'.$monthsAgo.$n, 'package' => $package,
                    'duration_months' => 6, 'location' => 'Palu', 'description' => 'Lowongan contoh yang sudah berakhir, untuk riwayat pendapatan di panel admin.',
                    'status' => 'ditutup', 'approved_at' => $approved, 'closes_at' => $approved->copy()->addDays(config("kabelota.packages.$package.days")),
                ])->forceFill(['created_at' => $approved->copy()->subDay()])->saveQuietly();
            }
        }

        // Offers from other companies too, so "Perusahaan teraktif" has a ranking.
        $alumni = Talent::where('type', 'alumni')->inRandomOrder()->take(6)->get();
        foreach ($alumni as $i => $talent) {
            $company = $companies[1 + $i % 3];
            RecruitmentOffer::create([
                'talent_id' => $talent->id, 'company_id' => $company->id, 'company_name' => $company->name,
                'contact_name' => $company->contact_name, 'contact_email' => 'hrd@contoh.kabelota.test',
                'position' => ['Site Engineer', 'Pengawas Lapangan', 'Drafter'][$i % 3], 'duration' => '6 bulan',
                'message' => 'Kami membutuhkan personel untuk paket pekerjaan tahun anggaran berjalan.',
                'status' => ['diterima', 'ditolak', 'menunggu'][$i % 3],
            ]);
        }

        RecruitmentOffer::query()->each(fn ($o) => DB::table('recruitment_offers')->where('id', $o->id)->update(['created_at' => $at(42)]));
        JobApplication::query()->each(fn ($a) => DB::table('job_applications')->where('id', $a->id)->update(['created_at' => $at(20)]));
    }

    private function fillAlumni(Talent $t): void
    {
        $jabatan = collect(config('kabelota.jabatan_kerja'))->filter(fn ($c) => $c === $t->concentration)->keys();
        $years = $t->experienceYears();
        $jenjang = match (true) { $years >= 14 => 9, $years >= 8 => 8, $years >= 4 => 7, default => fake()->numberBetween(5, 6) };

        if ($years >= 1) {
            foreach ($jabatan->shuffle()->take(fake()->numberBetween(1, 2)) as $j) {
                $issued = today()->subMonths(fake()->numberBetween(4, 60));
                $t->certifications()->create([
                    'jabatan_kerja' => $j,
                    'jenjang' => $jenjang,
                    'registration_number' => $jenjang.'.'.fake()->numerify('#.#.#.##.####'),
                    'issued_at' => $issued,
                    'expires_at' => $issued->copy()->addYears(5),
                ]);
            }
        }

        $t->update(['headline' => $t->certifications()->value('jabatan_kerja') ?? 'Tenaga ahli '.strtolower($t->concentration)]);

        $pool = collect(self::PROJECTS)->sortByDesc(fn ($p) => $p[3] === $t->concentration)->take(6)->shuffle();
        $year = $t->experience_since ?? now()->year;
        foreach ($pool->take(min(4, max(1, intdiv($years, 2)))) as [$name, $loc, $client]) {
            $start = fake()->numberBetween($year, max($year, now()->year - 1));
            $t->projects()->create([
                'name' => $name,
                'location' => $loc,
                'client' => $client,
                'contractor' => fake()->randomElement(self::CONTRACTORS),
                'position' => $position = fake()->randomElement(array_keys(self::POSITIONS)),
                'description' => self::POSITIONS[$position],
                'year_start' => $start,
                'year_end' => min(now()->year, $start + fake()->numberBetween(0, 2)),
            ]);
        }
    }

    private function fillStudent(Talent $t): void
    {
        $t->projects()->create([
            'name' => fake()->randomElement(['Asisten Laboratorium Mekanika Tanah', 'Asisten Praktikum Ilmu Ukur Tanah', 'Panitia Civil Week Untad', 'Kerja Praktik Proyek Gedung RSUD Undata']),
            'location' => 'Palu',
            'client' => 'Jurusan Teknik Sipil Untad',
            'contractor' => '-',
            'position' => fake()->randomElement(['Asisten', 'Anggota', 'Koordinator']),
            'description' => null,
            'year_start' => now()->year - 1,
            'year_end' => now()->year,
        ]);
    }

    private function password(string $key): string
    {
        $password = config("kabelota.$key");

        if (blank($password) && ! app()->environment('local', 'testing')) {
            throw new \RuntimeException("Isi KABELOTA_".strtoupper($key)." di .env sebelum mengisi data di server.");
        }

        return $password ?: 'password';
    }
}
