<?php

namespace App\Http\Controllers;

use App\Enums\Availability;
use App\Models\Talent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Profile builder for a signed-in talent. The status (alumni or mahasiswa) is
 * chosen here, not at sign-up, so one account can move from student to alumni.
 * Demo: the created profile id lives in the session instead of a user account.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        return view('profile.edit', [
            'talent' => Talent::with(['certifications', 'projects'])->where('user_id', $request->user()->id)->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $alumni = $request->input('type') === 'alumni';
        $year = now()->year;

        $data = $request->validate([
            'type' => ['required', Rule::in(['alumni', 'mahasiswa'])],
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:120'],
            'city' => ['required', Rule::in(config('kabelota.locations'))],
            'bio' => ['nullable', 'string', 'max:500'],
            'concentration' => ['required', Rule::in(config('kabelota.concentrations'))],
            'gpa' => ['nullable', 'numeric', 'between:0,4'],
            'graduation_year' => [Rule::requiredIf($alumni), 'nullable', 'integer', 'between:1990,'.$year],
            'experience_since' => [Rule::requiredIf($alumni), 'nullable', 'integer', 'between:1990,'.$year],
            'semester' => [Rule::requiredIf(! $alumni), 'nullable', 'integer', 'between:1,14'],
            'thesis_topic' => ['nullable', 'string', 'max:160'],
            'skk' => ['array'],
            'skk.*.jabatan_kerja' => ['nullable', Rule::in(array_keys(config('kabelota.jabatan_kerja')))],
            'skk.*.jenjang' => ['nullable', 'required_with:skk.*.jabatan_kerja', Rule::in(array_keys(config('kabelota.jenjang')))],
            'skk.*.registration_number' => ['nullable', 'required_with:skk.*.jabatan_kerja', 'string', 'max:40'],
            'skk.*.expires_at' => ['nullable', 'required_with:skk.*.jabatan_kerja', 'date'],
            'projects' => ['array'],
            'projects.*.name' => ['nullable', 'string', 'max:160'],
            'projects.*.position' => ['nullable', 'required_with:projects.*.name', 'string', 'max:80'],
            'projects.*.location' => ['nullable', 'string', 'max:80'],
            'projects.*.year_start' => ['nullable', 'required_with:projects.*.name', 'integer', 'between:1990,'.$year],
            'projects.*.description' => ['nullable', 'string', 'max:400'],
            'availability' => ['required', Rule::enum(Availability::class)],
            'preferred_locations' => ['array', 'min:1'],
            'preferred_locations.*' => [Rule::in(config('kabelota.locations'))],
            'consent' => ['accepted'],
            'skills' => ['array'],
            'skills.*' => [Rule::in(config('kabelota.skills'))],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('kabelota.upload.photo_kb')],
            'cv' => ['nullable', 'file', 'mimes:pdf', 'max:'.config('kabelota.upload.document_kb')],
            'skk_scan' => ['nullable', 'file', 'mimes:pdf', 'max:'.config('kabelota.upload.document_kb')],
            'transcript' => ['nullable', 'file', 'mimes:pdf', 'max:'.config('kabelota.upload.document_kb')],
            'hmts_member' => ['boolean'],
            'hmts_status' => ['nullable', 'required_if:hmts_member,1', Rule::in(array_keys(config('kabelota.hmts_statuses')))],
            'hmts_position' => ['nullable', 'string', 'max:80'],
        ], [
            'consent.accepted' => 'Centang persetujuan data untuk melanjutkan.',
            'preferred_locations.min' => 'Pilih minimal satu lokasi kerja.',
        ], [
            'name' => 'nama lengkap', 'phone' => 'nomor HP', 'city' => 'domisili', 'concentration' => 'konsentrasi',
            'gpa' => 'IPK', 'graduation_year' => 'tahun lulus', 'experience_since' => 'mulai bekerja', 'semester' => 'semester',
            'skk.*.jenjang' => 'jenjang', 'skk.*.registration_number' => 'nomor registrasi', 'skk.*.expires_at' => 'masa berlaku',
            'projects.*.position' => 'posisi', 'projects.*.year_start' => 'tahun',
            'hmts_status' => 'status keanggotaan', 'hmts_position' => 'jabatan',
            'photo' => 'foto', 'cv' => 'CV', 'skk_scan' => 'scan SKK', 'transcript' => 'transkrip',
        ]);

        $talent = DB::transaction(function () use ($data, $alumni, $request) {
            $talent = Talent::where('user_id', $request->user()->id)->first() ?? new Talent([
                'user_id' => $request->user()->id,
                'slug' => Str::slug(Str::before($data['name'], ',')).'-'.Str::lower(Str::random(4)),
            ]);
            $skk = collect($data['skk'] ?? [])->filter(fn ($c) => ! empty($c['jabatan_kerja']));

            $talent->fill([
                'type' => $data['type'],
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'city' => $data['city'],
                'bio' => $data['bio'] ?? null,
                'concentration' => $data['concentration'],
                'gpa' => $data['gpa'] ?? null,
                'graduation_year' => $alumni ? $data['graduation_year'] : null,
                'experience_since' => $alumni ? $data['experience_since'] : null,
                'semester' => $alumni ? null : $data['semester'],
                'thesis_topic' => $alumni ? null : ($data['thesis_topic'] ?? null),
                'availability' => $data['availability'],
                'preferred_locations' => $data['preferred_locations'],
                'skills' => $data['skills'] ?? [],
                'hmts_status' => ($data['hmts_member'] ?? false) ? $data['hmts_status'] : 'pasif',
                'hmts_position' => ($data['hmts_member'] ?? false) ? ($data['hmts_position'] ?? null) : null,
                'headline' => $skk->first()['jabatan_kerja'] ?? ($alumni ? 'Tenaga ahli '.Str::lower($data['concentration']) : 'Mahasiswa Teknik Sipil, konsentrasi '.Str::lower($data['concentration'])),
            ])->save();

            // Photo is public (shown on cards); documents stay on the private disk.
            if ($request->hasFile('photo')) {
                $talent->photo_path && Storage::disk('public')->delete($talent->photo_path);
                $talent->photo_path = $this->storePhoto($request->file('photo'));
            }
            foreach (['cv' => 'cv_path', 'skk_scan' => 'skk_scan_path', 'transcript' => 'transcript_path'] as $field => $column) {
                if ($request->hasFile($field)) {
                    $talent->{$column} && Storage::disk('local')->delete($talent->{$column});
                    $talent->{$column} = $request->file($field)->store("documents/{$field}", 'local');
                }
            }
            $talent->save();

            $signature = fn () => $talent->certifications()->get()
                ->map(fn ($c) => $c->jabatan_kerja.'|'.$c->jenjang.'|'.$c->registration_number.'|'.$c->expires_at->toDateString())
                ->sort()->implode(';');
            $before = $signature();

            $talent->certifications()->delete();
            if ($alumni) {
                foreach ($skk as $c) {
                    $talent->certifications()->create($c + ['issued_at' => now()->subYear()->toDateString()]);
                }
            }

            // A new scan or changed SKK data needs a fresh admin check.
            if ($request->hasFile('skk_scan') || $signature() !== $before) {
                $talent->update(['skk_verified_at' => null, 'skk_review_note' => null]);
            }

            $talent->projects()->delete();
            foreach (collect($data['projects'] ?? [])->filter(fn ($p) => ! empty($p['name'])) as $p) {
                $talent->projects()->create([
                    'name' => $p['name'], 'position' => $p['position'], 'location' => $p['location'] ?? '-',
                    'year_start' => $p['year_start'], 'year_end' => null, 'description' => $p['description'] ?? null,
                    'client' => '', 'contractor' => '-',
                ]);
            }

            return $talent;
        });

        return redirect()->route('talents.show', $talent)->with('status', 'Profil tersimpan. Beginilah tampilan profil Anda di mata perusahaan.');
    }

    /** Quick status switch from the public profile (e.g. a student who just graduated). */
    public function updateStatus(Request $request): RedirectResponse
    {
        $talent = Talent::where('user_id', $request->user()->id)->firstOrFail();
        $year = now()->year;

        $data = $request->validateWithBag('status', [
            'type' => ['required', Rule::in(['alumni', 'mahasiswa'])],
            'graduation_year' => ['required_if:type,alumni', 'nullable', 'integer', 'between:1990,'.$year],
            'experience_since' => ['required_if:type,alumni', 'nullable', 'integer', 'between:1990,'.$year],
            'semester' => ['required_if:type,mahasiswa', 'nullable', 'integer', 'between:1,14'],
        ], [], ['graduation_year' => 'tahun lulus', 'experience_since' => 'mulai bekerja', 'semester' => 'semester']);

        $alumni = $data['type'] === 'alumni';
        $talent->update([
            'type' => $data['type'],
            'graduation_year' => $alumni ? $data['graduation_year'] : null,
            'experience_since' => $alumni ? $data['experience_since'] : null,
            'semester' => $alumni ? null : $data['semester'],
            'headline' => $alumni
                ? ($talent->certifications()->value('jabatan_kerja') ?? 'Tenaga ahli '.Str::lower($talent->concentration))
                : 'Mahasiswa Teknik Sipil, konsentrasi '.Str::lower($talent->concentration),
        ]);

        return redirect()->route('talents.show', $talent)->with('status', $alumni
            ? 'Selamat atas kelulusannya. Profil Anda sekarang tampil sebagai alumni. Tambahkan SKK kalau sudah punya.'
            : 'Status diubah ke Mahasiswa. Profil Anda tampil dengan tanda Intern for Hire.');
    }

    /** Up to 5 MB in, ~600px WebP out, so cards stay light. Falls back to the original file. */
    private function storePhoto(\Illuminate\Http\UploadedFile $file): string
    {
        $src = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $src || ! function_exists('imagewebp')) {
            return $file->store('photos', 'public');
        }

        $size = min(imagesx($src), imagesy($src));
        $out = imagecreatetruecolor(600, 600);
        imagecopyresampled($out, $src, 0, 0, (int) ((imagesx($src) - $size) / 2), (int) ((imagesy($src) - $size) / 2), 600, 600, $size, $size);

        ob_start();
        imagewebp($out, null, 82);
        $path = 'photos/'.Str::random(32).'.webp';
        Storage::disk('public')->put($path, ob_get_clean());
        imagedestroy($src);
        imagedestroy($out);

        return $path;
    }
}
