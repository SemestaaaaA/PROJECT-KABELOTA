<?php

namespace Database\Factories;

use App\Enums\Availability;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Fictional demo talents. Names, phone numbers and emails are invented.
 *
 * @extends Factory<\App\Models\Talent>
 */
class TalentFactory extends Factory
{
    private const FIRST = ['Muh. Fadel', 'Nurul Hikmah', 'Rizky Ananda', 'Sri Wulandari', 'Ahmad Fauzan', 'Dewi Anggraini', 'Moh. Arif', 'Nur Afni', 'Andi Rahmat', 'Fitriani', 'Syahrul', 'Ningsih', 'Ilham', 'Rahmawati', 'Abdul Gafur', 'Sitti Nurhaliza', 'Fikri', 'Mutmainnah', 'Riswan', 'Indah Permata', 'Yusran', 'Nurfadillah', 'Agung', 'Hasnawati', 'Taufik', 'Ayu Lestari'];

    private const LAST = ['Lamarauna', 'Saleh', 'Pakaya', 'Lamba', 'Lasimpo', 'Bantilan', 'Tombolotutu', 'Lariwu', 'Paliudju', 'Labalado', 'Datupalinge', 'Rauf', 'Hamzah', 'Lapasere', 'Masyhuda', 'Tanjumbulu', 'Bakri', 'Lagarontu'];

    public function definition(): array
    {
        $name = fake()->randomElement(self::FIRST).' '.fake()->randomElement(self::LAST);
        $concentration = fake()->randomElement(config('kabelota.concentrations'));
        $graduation = fake()->numberBetween(2008, 2024);
        $city = fake()->randomElement(['Palu', 'Palu', 'Palu', 'Sigi', 'Donggala', 'Parigi Moutong', 'Poso', 'Morowali', 'Luar Sulawesi Tengah']);
        $prefs = collect(config('kabelota.locations'))->shuffle()->take(fake()->numberBetween(1, 3))->push($city)->unique()->values()->all();

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => $name.', S.T.',
            'type' => 'alumni',
            'headline' => 'Tenaga ahli '.Str::lower($concentration),
            'bio' => null,
            'concentration' => $concentration,
            'graduation_year' => $graduation,
            'gpa' => fake()->randomFloat(2, 2.9, 3.9),
            'city' => $city,
            'preferred_locations' => $prefs,
            'availability' => fake()->randomElement([Availability::Tersedia, Availability::Tersedia, Availability::Tersedia, Availability::TerikatKontrak, Availability::TerikatKontrak, Availability::TidakTersedia]),
            'experience_since' => min(now()->year, $graduation + fake()->numberBetween(0, 1)),
            'email' => Str::slug(explode(' ', $name)[0], '').fake()->numberBetween(10, 99).'@contoh.id',
            'phone' => '0812'.fake()->numerify('########'),
        ];
    }

    public function student(): static
    {
        return $this->state(function (array $attrs) {
            $name = str_replace(', S.T.', '', $attrs['name']);

            return [
                'name' => $name,
                'type' => 'mahasiswa',
                'headline' => 'Mahasiswa Teknik Sipil, konsentrasi '.Str::lower($attrs['concentration']),
                'graduation_year' => null,
                'semester' => fake()->numberBetween(5, 9),
                'experience_since' => null,
                'thesis_topic' => fake()->randomElement([
                    'Analisis likuifaksi tanah di Kelurahan Petobo',
                    'Evaluasi kinerja simpang bersinyal di Jl. Moh. Hatta, Palu',
                    'Perencanaan drainase kawasan Talise',
                    'Kuat tekan beton dengan agregat Sungai Palu',
                    'Analisis biaya dan waktu proyek gedung dengan metode earned value',
                    'Stabilitas lereng ruas jalan Kebun Kopi',
                ]),
            ];
        });
    }
}
