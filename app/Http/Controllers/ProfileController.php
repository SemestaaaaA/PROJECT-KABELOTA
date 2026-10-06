<?php

namespace App\Http\Controllers;

use App\Enums\Availability;
use App\Models\Talent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        if ($request->session()->get('demo_role') !== 'talenta') {
            return redirect()->route('home')->with('auth_required', 'talenta');
        }

        return view('profile.edit', [
            'talent' => Talent::with(['certifications', 'projects'])->find($request->session()->get('my_talent_id')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->session()->get('demo_role') === 'talenta', 403);

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
        ], [
            'consent.accepted' => 'Centang persetujuan data untuk melanjutkan.',
            'preferred_locations.min' => 'Pilih minimal satu lokasi kerja.',
        ], [
            'name' => 'nama lengkap', 'phone' => 'nomor HP', 'city' => 'domisili', 'concentration' => 'konsentrasi',
            'gpa' => 'IPK', 'graduation_year' => 'tahun lulus', 'experience_since' => 'mulai bekerja', 'semester' => 'semester',
            'skk.*.jenjang' => 'jenjang', 'skk.*.registration_number' => 'nomor registrasi', 'skk.*.expires_at' => 'masa berlaku',
            'projects.*.position' => 'posisi', 'projects.*.year_start' => 'tahun',
        ]);

        $talent = DB::transaction(function () use ($data, $alumni, $request) {
            $talent = Talent::find($request->session()->get('my_talent_id')) ?? new Talent(['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4))]);
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
                'headline' => $skk->first()['jabatan_kerja'] ?? ($alumni ? 'Tenaga ahli '.Str::lower($data['concentration']) : 'Mahasiswa Teknik Sipil, konsentrasi '.Str::lower($data['concentration'])),
            ])->save();

            $talent->certifications()->delete();
            if ($alumni) {
                foreach ($skk as $c) {
                    $talent->certifications()->create($c + ['issued_at' => now()->subYear()->toDateString()]);
                }
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

        $request->session()->put('my_talent_id', $talent->id);

        return redirect()->route('talents.show', $talent)->with('status', 'Profil tersimpan. Beginilah tampilan profil Anda di mata perusahaan.');
    }
}
