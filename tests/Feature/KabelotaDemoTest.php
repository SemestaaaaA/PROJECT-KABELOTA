<?php

namespace Tests\Feature;

use App\Enums\Availability;
use App\Models\Talent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KabelotaDemoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('membangun daerahnya', false);
        $this->get('/talenta')->assertOk()->assertSee('talenta cocok');
        $this->get('/talenta?view=list')->assertOk()->assertSee('<table', false);
        $this->get('/lowongan')->assertOk();
        $this->get('/tentang')->assertOk()->assertSee('Kenapa HMTS');
        $this->get('/kontak')->assertOk()->assertSee('hmts.tadulako');
        $this->get('/untuk-perusahaan')->assertOk()->assertSee('Rp200.000');
    }

    public function test_search_filters_by_jabatan_and_jenjang(): void
    {
        $response = $this->get('/talenta?view=list&jabatan=Ahli+Teknik+Jalan&jenjang[]=9');

        $expected = Talent::search(['jabatan' => 'Ahli Teknik Jalan', 'jenjang' => [9]])->get();
        $other = Talent::whereDoesntHave('certifications', fn ($q) => $q->where('jabatan_kerja', 'Ahli Teknik Jalan'))->first();

        $response->assertOk();
        $expected->each(fn ($t) => $response->assertSee(e($t->name), false));
        $response->assertDontSee(e($other->name), false);
    }

    public function test_profile_never_exposes_contact_details(): void
    {
        $talent = Talent::first();

        $this->get(route('talents.show', $talent))
            ->assertOk()
            ->assertDontSee($talent->phone)
            ->assertDontSee($talent->email);
    }

    public function test_offer_is_validated_and_stored(): void
    {
        $talent = Talent::where('availability', Availability::Tersedia)->first();

        // Without a company session the request bounces back to the login popup.
        $this->post(route('offers.store', $talent), ['company_name' => 'CV Uji'])
            ->assertRedirect(route('talents.show', $talent))
            ->assertSessionHas('auth_required', 'perusahaan');
        $this->assertDatabaseCount('recruitment_offers', 0);

        $this->withSession(['demo_role' => 'perusahaan']);

        $this->post(route('offers.store', $talent), ['company_name' => 'CV Uji'])
            ->assertSessionHasErrorsIn('offer', ['contact_name', 'contact_email', 'position', 'message']);

        $this->post(route('offers.store', $talent), [
            'company_name' => 'CV Uji',
            'contact_name' => 'Rina',
            'contact_email' => 'rina@contoh.id',
            'position' => 'Site Engineer',
            'message' => 'Kami membutuhkan site engineer untuk paket jalan.',
        ])->assertRedirect(route('talents.show', $talent))->assertSessionHas('offer_sent');

        $this->assertDatabaseHas('recruitment_offers', ['talent_id' => $talent->id, 'status' => 'menunggu']);
    }

    public function test_unavailable_talent_cannot_receive_offers(): void
    {
        $talent = Talent::where('availability', Availability::TidakTersedia)->firstOrFail();

        $this->withSession(['demo_role' => 'perusahaan'])->post(route('offers.store', $talent), [
            'company_name' => 'CV Uji', 'contact_name' => 'Rina', 'contact_email' => 'rina@contoh.id',
            'position' => 'Site Engineer', 'message' => 'Kami membutuhkan site engineer untuk paket jalan.',
        ])->assertStatus(422);
    }

    public function test_contact_form_stores_message(): void
    {
        $this->post('/kontak', ['name' => 'Rina', 'email' => 'x'])->assertSessionHasErrors(['email', 'message']);
        $this->post('/kontak', ['name' => 'Rina', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.'])
            ->assertRedirect('/kontak');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_talent_can_build_profile_as_student_then_switch_to_alumni(): void
    {
        $this->get('/profil')->assertRedirect('/');

        $this->withSession(['demo_role' => 'talenta']);
        $this->get('/profil')->assertOk()->assertSee('Buat profil');

        $base = [
            'name' => 'Rahmat Uji', 'phone' => '081200000000', 'email' => 'rahmat@contoh.id', 'city' => 'Palu',
            'concentration' => 'Struktur', 'availability' => 'tersedia', 'preferred_locations' => ['Palu'], 'consent' => '1',
            'projects' => [['name' => 'Asisten Lab Beton', 'position' => 'Asisten', 'location' => 'Palu', 'year_start' => 2025]],
        ];

        $this->post('/profil', $base + ['type' => 'mahasiswa', 'semester' => 7])->assertRedirect();
        $talent = Talent::where('email', 'rahmat@contoh.id')->firstOrFail();
        $this->assertSame('mahasiswa', $talent->type);
        $this->get(route('talents.show', $talent))->assertSee('Intern for Hire')->assertDontSee('081200000000');

        $this->post('/profil', $base + [
            'type' => 'alumni', 'graduation_year' => 2025, 'experience_since' => 2025,
            'skk' => [['jabatan_kerja' => 'Ahli Teknik Jalan', 'jenjang' => 6, 'registration_number' => '6.1.2.3', 'expires_at' => '2030-01-01']],
        ])->assertRedirect(route('talents.show', $talent));

        $talent->refresh();
        $this->assertSame('alumni', $talent->type);
        $this->assertSame('Ahli Teknik Jalan', $talent->headline);
        $this->assertSame(1, $talent->certifications()->count());
        $this->assertSame(1, Talent::where('email', 'rahmat@contoh.id')->count());
    }

    public function test_profile_requires_consent(): void
    {
        $this->withSession(['demo_role' => 'talenta'])
            ->post('/profil', ['type' => 'mahasiswa', 'name' => 'X'])
            ->assertSessionHasErrors(['consent', 'phone', 'semester']);
    }
}
