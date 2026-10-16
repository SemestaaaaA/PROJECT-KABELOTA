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
use App\Support\DeviceLabel;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function test_device_label_reads_common_user_agents(): void
    {
        $this->assertSame('Chrome di macOS', DeviceLabel::from('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36'));
        $this->assertSame('Safari di iPhone', DeviceLabel::from('Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Firefox di Windows', DeviceLabel::from('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0'));
        $this->assertSame('Edge di Windows', DeviceLabel::from('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0'));
        $this->assertSame('Chrome di Android', DeviceLabel::from('Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36'));
        $this->assertSame('Perangkat tidak dikenal', DeviceLabel::from(null));
        $this->assertSame('Perangkat tidak dikenal', DeviceLabel::from('curl/8.7.1'));
    }

    /** Session rows as the database session driver writes them; the test session itself uses the array driver. */
    private function sessionRow(User $user, string $id, string $agent, int $minutesAgo, string $ip = '10.0.0.1'): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => $ip, 'user_agent' => $agent,
            'payload' => base64_encode('rahasia-payload'), 'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    public function test_profile_lists_this_admins_sessions_only(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $current = session()->getId();
        $this->sessionRow($admin, $current, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 0, '10.0.0.7');
        $this->sessionRow($admin, 'sesi-lain-iphone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1', 30, '10.0.0.8');
        $this->sessionRow($other, 'sesi-admin-lain', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0', 5, '10.9.9.9');

        Livewire::test(EditProfile::class)
            ->assertSee('Sesi aktif')
            ->assertSee('Chrome di macOS')
            ->assertSee('Perangkat ini')
            ->assertSee('Safari di iPhone')
            ->assertSee('10.0.0.8')
            ->assertSee('30 menit')
            ->assertDontSee('Firefox di Windows')
            ->assertDontSee('10.9.9.9')
            ->assertDontSee('rahasia-payload');
    }

    public function test_logging_out_other_devices_needs_the_current_password(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['password' => 'sandi-uji-panjang-1', 'remember_token' => 'token-lama-uji'])->save();
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $current = session()->getId();
        $this->sessionRow($admin, $current, 'Mozilla/5.0 (Macintosh) Chrome/129.0', 0);
        $this->sessionRow($admin, 'sesi-lain-1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) Version/17.6 Mobile Safari/604.1', 30);
        $this->sessionRow($admin, 'sesi-lain-2', 'Mozilla/5.0 (Windows NT 10.0) Firefox/131.0', 90);
        $this->sessionRow($other, 'sesi-admin-lain', 'Mozilla/5.0 (Windows NT 10.0) Firefox/131.0', 5);

        Livewire::test(EditProfile::class)
            ->callAction('logoutOtherDevices', data: ['password' => 'salah-sandi'])
            ->assertHasActionErrors(['password']);
        $this->assertSame(3, DB::table('sessions')->where('user_id', $admin->id)->count());

        // Mounted the way the button in the "Sesi aktif" header does it (schema component context).
        Livewire::test(EditProfile::class)
            ->callAction(TestAction::make('logoutOtherDevices')->schemaComponent('sessions', schema: 'content'), data: ['password' => 'sandi-uji-panjang-1'])
            ->assertHasNoActionErrors()
            ->assertNotified('2 sesi lain dikeluarkan');

        $this->assertSame([$current], DB::table('sessions')->where('user_id', $admin->id)->pluck('id')->all());
        // Remember-me cookies on other devices stop working; this device stays signed in with a matching hash.
        $this->assertNotSame('token-lama-uji', $admin->fresh()->getRememberToken());
        $this->assertAuthenticatedAs($admin);
        $this->assertSame($admin->fresh()->getAuthPassword(), session('password_hash_web'));
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertTrue(Hash::check('sandi-uji-panjang-1', $admin->fresh()->password));

        Livewire::test(EditProfile::class)->assertActionHidden('logoutOtherDevices');
    }

    public function test_profile_shows_the_last_ten_activities_of_this_admin(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'admin']);
        $company = $this->pendingCompany();
        foreach (range(1, 12) as $n) {
            AdminActivity::create(['user_id' => $admin->id, 'action' => 'company_verified', 'subject_type' => $company->getMorphClass(), 'subject_id' => $company->id, 'description' => "Aktivitas uji nomor $n."])
                ->forceFill(['created_at' => now()->subMinutes(100 - $n)])->save();
        }
        AdminActivity::create(['user_id' => $other->id, 'action' => 'job_rejected', 'subject_type' => $company->getMorphClass(), 'subject_id' => $company->id, 'description' => 'Milik admin lain.']);
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->assertSee('Aktivitas terakhir Anda')
            ->assertSee('Aktivitas uji nomor 12.')
            ->assertSee('Aktivitas uji nomor 3.')
            ->assertDontSee('Aktivitas uji nomor 2.')
            ->assertDontSee('Milik admin lain.')
            ->assertSee('Perusahaan diverifikasi')
            ->assertSee('Lihat semua');

        Livewire::withQueryParams(['filters' => ['user_id' => ['value' => (string) $other->id]]])
            ->test(ListAdminActivities::class)
            ->assertSee('Milik admin lain.')
            ->assertDontSee('Aktivitas uji nomor 12.');
    }

    public function test_this_device_keeps_its_remember_me_cookie_after_logging_out_others(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['password' => 'sandi-uji-panjang-1', 'remember_token' => 'token-lama-uji'])->save();
        $this->actingAs($admin);
        $this->sessionRow($admin, 'sesi-lain-1', 'Mozilla/5.0 (Windows NT 10.0) Firefox/131.0', 30);
        $recaller = auth()->guard('web')->getRecallerName();

        Livewire::withCookie($recaller, $admin->id.'|token-lama-uji|'.hash_hmac('sha256', $admin->getAuthPassword(), config('app.key')))
            ->test(EditProfile::class)
            ->callAction('logoutOtherDevices', data: ['password' => 'sandi-uji-panjang-1'])
            ->assertHasNoActionErrors();

        $fresh = $admin->fresh();
        $queued = \Illuminate\Support\Facades\Cookie::queued($recaller);
        $this->assertNotNull($queued, 'A new recaller cookie is queued for this device.');
        [$id, $token] = explode('|', $queued->getValue());
        $this->assertSame((string) $admin->id, $id);
        $this->assertSame($fresh->getRememberToken(), $token);
        $this->assertNotSame('token-lama-uji', $token);
    }

    public function test_email_change_from_the_profile_is_saved(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['password' => 'sandi-uji-panjang-1'])->save();
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'admin-baru@kabelota.test'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);
        $this->assertNotSame('admin-baru@kabelota.test', $admin->fresh()->email);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'admin-baru@kabelota.test', 'currentPassword' => 'sandi-uji-panjang-1'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('admin-baru@kabelota.test', $admin->fresh()->email);
    }
}
