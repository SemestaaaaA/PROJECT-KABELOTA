<?php

namespace Tests\Feature;

use App\Filament\Widgets;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_every_dashboard_widget_renders_without_n_plus_one(): void
    {
        Model::preventLazyLoading();
        $this->actingAs(User::where('role', 'admin')->firstOrFail());

        $widgets = [
            Widgets\ActionQueue::class, Widgets\KabelotaStats::class, Widgets\RegistrationsChart::class, Widgets\RevenueChart::class,
            Widgets\ConcentrationChart::class, Widgets\JenjangChart::class, Widgets\RecruitmentFunnelChart::class, Widgets\LocationChart::class,
            Widgets\ExpiringJobsTable::class, Widgets\TopCompaniesTable::class, Widgets\DataQualityStats::class,
            Widgets\AdminNotificationsWidget::class, Widgets\InboxWidget::class,
        ];

        foreach ($widgets as $widget) {
            Livewire::test($widget)->assertOk();
        }

        Livewire::test(Widgets\ActionQueue::class)->assertSee('Verifikasi perusahaan')->assertSee('Scan SKK perlu dicek');
        Livewire::test(Widgets\KabelotaStats::class)->assertSee('Pendapatan lowongan')->assertSee('Rp');
        Livewire::test(Widgets\TopCompaniesTable::class)->assertSee('CV Lembah Palu Konsultan');

        Model::preventLazyLoading(false);
    }

    public function test_visitors_panel_only_when_umami_share_url_is_set(): void
    {
        $this->assertFalse(Widgets\VisitorsWidget::canView());

        config(['kabelota.analytics' => ['host' => 'https://cloud.umami.is', 'website_id' => 'x', 'share_url' => 'https://cloud.umami.is/share/abc/kabelota']]);
        $this->assertTrue(Widgets\VisitorsWidget::canView());
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        Livewire::test(Widgets\VisitorsWidget::class)->assertSee('https://cloud.umami.is/share/abc/kabelota', false);

        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-src 'self' https://cloud.umami.is", $csp);
    }

    public function test_admin_uses_the_kabelota_theme(): void
    {
        $admin = \App\Models\User::where('role', 'admin')->firstOrFail();
        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        // Built assets are hashed (build/assets/theme-xxxx.css), so resolve the theme entry through the manifest.
        $this->assertStringContainsString(\Illuminate\Support\Facades\Vite::asset('resources/css/filament/admin/theme.css'), $html);
        $this->assertStringContainsString("--font-family: 'Barlow'", $html);
        $this->assertStringContainsString('font-family: "Barlow"', $html); // @font-face from the self-hosted @fonts
        $this->assertStringNotContainsString('fonts.bunny.net', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }

    public function test_dashboard_is_a_bento_with_other_analytics_folded(): void
    {
        $admin = \App\Models\User::where('role', 'admin')->firstOrFail();
        \App\Support\AdminAlerts::send('payment_proof', 'Bukti bayar lowongan masuk', 'CV Uji: Drafter (Reguler).', '/admin/job-postings');
        \App\Models\ContactMessage::create(['name' => 'Rina Uji', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);

        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        foreach (['Perlu tindakan', 'Notifikasi', 'Pesan masuk', 'Analitik lainnya'] as $heading) {
            $this->assertStringContainsString($heading, $html);
        }
        $this->assertTrue(strpos($html, 'Perlu tindakan') < strpos($html, 'Notifikasi'));
        $this->assertTrue(strpos($html, 'Notifikasi') < strpos($html, 'Analitik lainnya'));
        $this->assertStringContainsString('kb-bento', $html);

        // "Analitik lainnya" starts folded: the section's Alpine state renders collapsed.
        $section = substr($html, 0, strpos($html, 'Analitik lainnya'));
        $section = substr($section, strrpos($section, '<section'));
        $this->assertMatchesRegularExpression('/isCollapsed:\s*true/', $section);

        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Widgets\AdminNotificationsWidget::class)->assertSee('Bukti bayar lowongan masuk')->assertSee('1 baru');
        \Livewire\Livewire::test(\App\Filament\Widgets\InboxWidget::class)->assertSee('Rina Uji')->assertSee('1 baru');
    }

    public function test_opening_a_dashboard_notification_marks_only_your_own_read(): void
    {
        $admin = \App\Models\User::where('role', 'admin')->firstOrFail();
        $other = \App\Models\User::factory()->create(['role' => 'admin']);
        \App\Support\AdminAlerts::send('payment_proof', 'Bukti bayar lowongan masuk', 'CV Uji: Drafter (Reguler).', '/admin/job-postings');
        $mine = $admin->notifications()->firstOrFail();
        $theirs = $other->notifications()->firstOrFail();

        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Widgets\AdminNotificationsWidget::class)
            ->call('open', $theirs->id)
            ->assertNoRedirect()
            ->call('open', $mine->id)
            ->assertRedirect('/admin/job-postings');

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_inbox_rows_deep_link_to_the_message(): void
    {
        $message = \App\Models\ContactMessage::create(['name' => 'Rina Uji', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);
        $this->actingAs(\App\Models\User::where('role', 'admin')->firstOrFail());

        $url = \App\Filament\Resources\ContactMessages\ContactMessageResource::getUrl('index', ['tableAction' => 'view', 'tableActionRecord' => $message->id]);
        \Livewire\Livewire::test(\App\Filament\Widgets\InboxWidget::class)->assertSee(e($url), false);

        // Filament mounts the ?tableAction= modal from the page's wire:init; replay that call here.
        $this->get($url)->assertOk()->assertSee('wire:init="mountAction(', false);
        $page = \Livewire\Livewire::withQueryParams(['tableAction' => 'view', 'tableActionRecord' => $message->id])
            ->test(\App\Filament\Resources\ContactMessages\Pages\ListContactMessages::class);
        $page->call('mountAction', 'view', [], $page->instance()->getDefaultTableActionUrlContext())
            ->assertSee('rina@contoh.id');
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_notifications_and_inbox_widgets_show_latest_items(): void
    {
        $admin = \App\Models\User::where('role', 'admin')->firstOrFail();
        \App\Support\AdminAlerts::send('payment_proof', 'Bukti bayar lowongan masuk', 'CV Uji: Drafter (Reguler).', '/admin/job-postings');
        \App\Models\ContactMessage::create(['name' => 'Rina Uji', 'email' => 'rina@contoh.id', 'topic' => 'Pertanyaan umum', 'message' => 'Halo, mau tanya soal pilot.']);

        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Widgets\AdminNotificationsWidget::class)
            ->assertSee('Bukti bayar lowongan masuk')
            ->call('markAllRead');
        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());

        \Livewire\Livewire::test(\App\Filament\Widgets\InboxWidget::class)->assertSee('Rina Uji');
    }
}
