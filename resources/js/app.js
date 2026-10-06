import '@phosphor-icons/web/regular';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

Alpine.plugin(collapse);
Alpine.plugin(focus);
window.Alpine = Alpine;

// Global login/daftar popup. Open from anywhere with:
//   $dispatch('auth', { tab: 'daftar', role: 'perusahaan', reason: '...' })
document.addEventListener('alpine:init', () => {
    Alpine.store('auth', {
        open: false,
        tab: 'masuk',
        role: 'talenta',
        reason: '',
        show(detail = {}) {
            this.tab = detail.tab ?? 'masuk';
            this.role = detail.role ?? this.role;
            this.reason = detail.reason ?? '';
            this.open = true;
        },
    });
});
document.addEventListener('alpine:init', () => {
    // Shared theme state so the header switch and the mobile menu stay in sync.
    Alpine.store('theme', {
        dark: document.documentElement.dataset.theme
            ? document.documentElement.dataset.theme === 'dark'
            : matchMedia('(prefers-color-scheme: dark)').matches,
        toggle() {
            this.dark = !this.dark;
            document.documentElement.dataset.theme = this.dark ? 'dark' : 'light';
            try { localStorage.setItem('kabelota-theme', this.dark ? 'dark' : 'light'); } catch (e) {}
        },
    });
    Alpine.store('apply', { job: null, show(job) { this.job = job; } });
});
window.addEventListener('auth', (e) => Alpine.store('auth').show(e.detail));
Alpine.start();
