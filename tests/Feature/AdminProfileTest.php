<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\AdminActivities\Pages\ListAdminActivities;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\JobPostings\Pages\ListJobPostings;
use App\Filament\Resources\Talent\Pages\ListTalent;
use App\Models\AdminActivity;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Talent;
use App\Models\User;
use App\Notifications\AdminAlertMail;
use App\Support\AdminAlerts;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin 4c: activity log, custom profile page (photo, notification preferences, sessions). */
class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading();
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function pendingCompany(): Company
    {
        return Company::create(['name' => 'PT Uji Riwayat', 'type' => 'konsultan', 'city' => 'Palu', 'status' => 'menunggu']);
    }

    public function test_verifying_a_company_records_an_activity(): void
    {
        $admin = $this->admin();
        $company = $this->pendingCompany();
        $this->actingAs($admin);

        Livewire::test(ListCompanies::class)->callTableAction('verifikasi', $company);

        $activity = AdminActivity::sole();
        $this->assertSame('company_verified', $activity->action);
        $this->assertSame($admin->id, $activity->user_id);
        $this->assertTrue($activity->subject->is($company));
        $this->assertStringContainsString('PT Uji Riwayat', $activity->description);
    }

    public function test_approving_a_job_and_rejecting_skk_record_activities(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $job = JobPosting::create([
            'company_id' => $this->pendingCompany()->id, 'title' => 'Lowongan Uji Riwayat', 'package' => 'reguler', 'status' => 'menunggu_verifikasi',
            'location' => 'Palu', 'duration_months' => 3, 'description' => 'Uji.', 'closes_at' => today()->addDays(10),
        ]);
        $talent = Talent::factory()->create(['skk_scan_path' => 'skk/uji.pdf', 'skk_review_note' => null]);

        Livewire::test(ListJobPostings::class)->callTableAction('setujui', $job);
        Livewire::test(ListTalent::class)->callTableAction('tolak_skk', $talent, data: ['note' => 'Nomor registrasi tidak terbaca.']);

        $approved = AdminActivity::where('action', 'job_approved')->sole();
        $this->assertTrue($approved->subject->is($job));
        $this->assertSame($admin->id, $approved->user_id);

        $rejected = AdminActivity::where('action', 'skk_rejected')->sole();
        $this->assertTrue($rejected->subject->is($talent));
        $this->assertStringContainsString('Nomor registrasi tidak terbaca.', $rejected->description);
    }

    public function test_activity_log_page_lists_entries_for_admins_only(): void
    {
        $admin = $this->admin();
        $company = $this->pendingCompany();
        $this->actingAs($admin);
        Livewire::test(ListCompanies::class)->callTableAction('verifikasi', $company);
        $activity = AdminActivity::sole();

        Livewire::test(ListAdminActivities::class)
            ->assertCanSeeTableRecords([$activity])
            ->assertSee('Perusahaan diverifikasi')
            ->assertSee('PT Uji Riwayat');
        $this->get('/admin/admin-activities')->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'talenta']))->get('/admin/admin-activities')->assertForbidden();
    }

    public function test_profile_page_uses_the_custom_page_and_keeps_two_factor(): void
    {
        $this->actingAs($this->admin())->get('/admin/profile')
            ->assertOk()
            ->assertSee('Foto profil')
            ->assertSee('Aplikasi authenticator');
    }

    public function test_admin_uploads_a_profile_photo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->set('data.avatar_path', UploadedFile::fake()->image('a.png', 400, 400))
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $admin->fresh()->avatar_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('avatars/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($admin->fresh()->getFilamentAvatarUrl());
    }

    public function test_replacing_or_removing_the_photo_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)->set('data.avatar_path', UploadedFile::fake()->image('a.png', 400, 400))->call('save');
        $first = $admin->fresh()->avatar_path;

        // Like the browser: the field first drops the current photo, then uploads the new one.
        Livewire::test(EditProfile::class)
            ->set('data.avatar_path', null)
            ->set('data.avatar_path', [UploadedFile::fake()->image('b.png', 300, 300)])
            ->call('save')
            ->assertHasNoFormErrors();
        $second = $admin->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        Livewire::test(EditProfile::class)->set('data.avatar_path', null)->call('save')->assertHasNoFormErrors();
        $this->assertNull($admin->fresh()->avatar_path);
        $this->assertNull($admin->fresh()->getFilamentAvatarUrl());
        Storage::disk('public')->assertMissing($second);
    }

    public function test_profile_photo_path_cannot_be_pointed_at_another_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/lain.webp', 'x');
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)->set('data.avatar_path', ['logos/lain.webp'])->call('save')->assertHasFormErrors(['avatar_path']);

        $this->assertNull($admin->fresh()->avatar_path);
    }

    public function test_name_and_email_still_save_from_the_profile(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->fillForm(['name' => 'Admin Baru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Admin Baru', $admin->fresh()->name);
        $this->assertNull($admin->fresh()->getFilamentAvatarUrl());
    }

    public function test_alerts_follow_each_admins_channel_preference(): void
    {
        Notification::fake();
        $default = $this->admin();
        $muted = User::factory()->create(['role' => 'admin']);
        $muted->forceFill(['notification_prefs' => ['contact_message' => 'off']])->save();
        $mailed = User::factory()->create(['role' => 'admin']);
        $mailed->forceFill(['notification_prefs' => ['contact_message' => 'bell_email']])->save();

        AdminAlerts::send('contact_message', 'Pesan kontak: Uji', 'Rina: [klik](https://evil.example)', 'https://kabelota.test/admin/contact-messages');

        $this->assertSame('bell', $default->adminAlertChannel('contact_message'));
        Notification::assertSentToTimes($default, DatabaseNotification::class, 1);
        Notification::assertNotSentTo($muted, DatabaseNotification::class);
        Notification::assertSentToTimes($mailed, DatabaseNotification::class, 1);

        Notification::assertSentTo($mailed, AdminAlertMail::class, function (AdminAlertMail $mail) use ($mailed) {
            $message = $mail->toMail($mailed);

            return $message->subject === 'Pesan kontak: Uji'
                && $message->actionText === 'Buka di admin'
                && $message->actionUrl === 'https://kabelota.test/admin/contact-messages'
                && ! str_contains(implode(' ', $message->introLines), '[klik](');
        });
        Notification::assertNotSentTo($default, AdminAlertMail::class);
        Notification::assertNotSentTo($muted, AdminAlertMail::class);

        // Other events keep the default (bell only) for the muted admin.
        AdminAlerts::send('job_failed', 'Job gagal: Uji', 'Galat.', 'https://kabelota.test/admin');
        Notification::assertSentToTimes($muted, DatabaseNotification::class, 1);
        Notification::assertSentToTimes($mailed, AdminAlertMail::class, 1);
    }

    public function test_profile_saves_notification_preferences(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->assertSet('data.notification_prefs.contact_message', 'bell')
            ->set('data.notification_prefs.contact_message', 'off')
            ->set('data.notification_prefs.job_failed', 'bell_email')
            ->set('data.notification_prefs.bukan_kejadian', 'bell_email')
            ->call('save')
            ->assertHasNoFormErrors();

        $prefs = $admin->fresh()->notification_prefs;
        $this->assertSame('off', $prefs['contact_message']);
        $this->assertSame('bell_email', $prefs['job_failed']);
        $this->assertSame('bell', $prefs['payment_proof']);
        $this->assertArrayNotHasKey('bukan_kejadian', $prefs);
        $this->assertSame(array_keys(AdminAlerts::EVENTS), array_keys($prefs));

        Livewire::test(EditProfile::class)
            ->assertSet('data.notification_prefs.contact_message', 'off')
            ->set('data.notification_prefs.contact_message', 'sms')
            ->call('save')
            ->assertHasFormErrors(['notification_prefs.contact_message']);

        $this->assertSame('off', $admin->fresh()->notification_prefs['contact_message']);
        $this->actingAs($admin)->get('/admin/profile')->assertOk()->assertSee('Notifikasi')->assertSee('Lonceng + email');
    }
}
