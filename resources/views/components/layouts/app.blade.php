@php($me = auth()->user())
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Kabelota · Cari Tenaga Ahli Teknik Sipil Sulawesi Tengah' }}</title>
    <meta name="description" content="{{ $description ?? 'Cari alumni dan mahasiswa Teknik Sipil UNTAD berdasarkan SKK, jenjang, dan pengalaman. Gratis untuk talenta.' }}">
    {{-- Link previews (WhatsApp, LinkedIn, Telegram). --}}
    <meta property="og:site_name" content="Kabelota">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ?? 'Kabelota · Cari Tenaga Ahli Teknik Sipil Sulawesi Tengah' }}">
    <meta property="og:description" content="{{ $description ?? 'Cari alumni dan mahasiswa Teknik Sipil UNTAD berdasarkan SKK, jenjang, dan pengalaman. Gratis untuk talenta.' }}">
    <meta property="og:image" content="{{ url('/images/og.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    @unless (app()->isProduction())
        <meta name="robots" content="noindex">
    @endunless
    <link rel="canonical" href="{{ url()->current() }}">
    @if (config('kabelota.analytics.host') && config('kabelota.analytics.website_id'))
        {{-- Umami: cookie-free page view counts, no personal data. --}}
        <script defer src="{{ rtrim(config('kabelota.analytics.host'), '/') }}/script.js" data-website-id="{{ config('kabelota.analytics.website_id') }}"></script>
    @endif
    {{ $head ?? '' }}
    <link rel="icon" href="/favicon.ico">
    <script>
        // Apply the saved theme before paint to avoid a flash.
        try { const t = localStorage.getItem('kabelota-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data @if (session('open_auth') || $errors->login->any() || $errors->register->any()) x-init="$store.auth.show({ tab: @js($errors->register->any() ? 'daftar' : (session('open_auth') ?? 'masuk')), role: @js(old('role', 'talenta')), reason: @js(session('auth_reason', '')) })" @endif>
    @if (app()->environment('staging'))
        <div class="demo-ribbon qa-ribbon"><b>Versi QA.</b> Ini server uji coba, bukan versi demo.
            @if (config('kabelota.qa_form_url'))<a href="{{ config('kabelota.qa_form_url') }}" target="_blank" rel="noopener">Laporkan masalah</a>@endif
        </div>
    @elseif (config('kabelota.demo_mode'))
        <div class="demo-ribbon"><b>Mode demo.</b> Semua nama talenta, perusahaan, dan proyek adalah data contoh.</div>
    @endif

    <div class="topbar" x-data="{ scrolled: false, menu: false }" @scroll.window.throttle.100ms="scrolled = window.scrollY > 8"
        :class="{ scrolled, 'menu-open': menu }" @keydown.escape.window="menu = false" x-effect="document.documentElement.classList.toggle('no-scroll', menu)">
    <header class="wrap nav">
        <a class="logo" href="{{ route('home') }}" aria-label="Kabelota, ke beranda">
            <img class="for-light" src="/images/brand/kabelota-hitam.webp" alt="Kabelota" width="140" height="30">
            <img class="for-dark" src="/images/brand/kabelota-putih.webp" alt="" width="140" height="30">
        </a>
        <nav aria-label="Menu utama">
            <a href="{{ route('talents.index') }}" @if (request()->routeIs('talents.*')) aria-current="page" @endif>Cari Talenta</a>
            <a href="{{ route('jobs.index') }}" @if (request()->routeIs('jobs.*')) aria-current="page" @endif>Lowongan</a>
            <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>Tentang Kami</a>
            <a href="{{ route('companies') }}" @if (request()->routeIs('companies')) aria-current="page" @endif>Untuk Perusahaan</a>
            <a href="{{ route('contact') }}" @if (request()->routeIs('contact')) aria-current="page" @endif>Kontak</a>
        </nav>
        <div class="right">
            <button type="button" class="theme-switch desk-only" aria-label="Ganti tema terang atau gelap" @click="$store.theme.toggle()"
                :aria-label="$store.theme.dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'" :title="$store.theme.dark ? 'Mode terang' : 'Mode gelap'">
                <i class="ph" :class="$store.theme.dark ? 'ph-sun' : 'ph-moon'" aria-hidden="true"></i>
            </button>
            @auth
                @php($pending = $me->pendingOfferCount())
                <div class="acct desk-only" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="acct-btn" @click="open = !open" :aria-expanded="open" aria-haspopup="menu">
                        <span class="acct-av" aria-hidden="true">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                        <span class="acct-name">{{ $me->isCompany() ? ($me->company?->name ?? $me->name) : \Illuminate\Support\Str::before($me->name, ',') }}</span>
                        @if ($pending)<span class="count" aria-label="{{ $pending }} tawaran baru">{{ $pending }}</span>@endif
                        <i class="ph ph-caret-down" aria-hidden="true"></i>
                    </button>
                    <div class="acct-menu" role="menu" x-show="open" x-cloak x-transition.opacity>
                        @if ($me->isTalent())
                            <a role="menuitem" href="{{ $me->talent ? route('talents.show', $me->talent) : route('profile.edit') }}"><i class="ph ph-user" aria-hidden="true"></i> Profil saya</a>
                            <a role="menuitem" href="{{ route('talent.offers') }}"><i class="ph ph-tray" aria-hidden="true"></i> Tawaran masuk @if ($pending)<span class="count">{{ $pending }}</span>@endif</a>
                            <a role="menuitem" href="{{ route('talent.applications') }}"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i> Lamaran saya</a>
                        @elseif ($me->isCompany())
                            <a role="menuitem" href="{{ route('company.jobs') }}"><i class="ph ph-briefcase" aria-hidden="true"></i> Lowongan saya</a>
                            <a role="menuitem" href="{{ route('company.offers') }}"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i> Tawaran terkirim</a>
                            <a role="menuitem" href="{{ route('jobs.posting.create') }}"><i class="ph ph-plus" aria-hidden="true"></i> Pasang lowongan</a>
                            <a role="menuitem" href="{{ route('company.profile') }}"><i class="ph ph-buildings" aria-hidden="true"></i> Profil perusahaan
                                @unless ($me->isVerifiedCompany())<span class="count warn">!</span>@endunless</a>
                        @elseif ($me->isAdmin())
                            <a role="menuitem" href="/admin"><i class="ph ph-gauge" aria-hidden="true"></i> Panel admin</a>
                        @endif
                        <a role="menuitem" href="{{ route('account') }}"><i class="ph ph-gear-six" aria-hidden="true"></i> Pengaturan akun</a>
                        <form method="post" action="{{ route('logout') }}">@csrf<button role="menuitem" type="submit"><i class="ph ph-sign-out" aria-hidden="true"></i> Keluar</button></form>
                    </div>
                </div>
            @else
                <button type="button" class="in desk-only" @click="$store.auth.show({ tab: 'masuk' })">Masuk</button>
                <button type="button" class="btn btn-accent btn-sm" @click="$store.auth.show({ tab: 'daftar' })">Daftar</button>
            @endauth
            <button type="button" class="burger" aria-label="Buka menu" @click="menu = !menu" :aria-expanded="menu" aria-controls="mnav" :aria-label="menu ? 'Tutup menu' : 'Buka menu'">
                <i class="ph" :class="menu ? 'ph-x' : 'ph-list'" aria-hidden="true"></i>
                @auth @if ($me->pendingOfferCount())<span class="dot-count" aria-hidden="true">{{ $me->pendingOfferCount() }}</span>@endif @endauth
            </button>
        </div>
    </header>

    {{-- Mobile menu (below 900px) --}}
    <div id="mnav" class="mnav" x-show="menu" x-cloak x-transition.opacity.duration.150ms @click.self="menu = false">
        <div class="mnav-panel" role="dialog" aria-modal="true" aria-label="Menu" x-trap="menu">
            @auth
                <div class="mnav-me">
                    <span class="acct-av" aria-hidden="true">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                    <div><b>{{ $me->isCompany() ? ($me->company?->name ?? $me->name) : \Illuminate\Support\Str::before($me->name, ',') }}</b>
                        <span class="demo-note">{{ ['talenta' => 'Talenta', 'perusahaan' => 'Perusahaan', 'admin' => 'Admin'][$me->role] }}</span></div>
                </div>
            @endauth
            <nav aria-label="Menu utama" class="mnav-links">
                <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif><i class="ph ph-house" aria-hidden="true"></i> Beranda</a>
                <a href="{{ route('talents.index') }}" @if (request()->routeIs('talents.*')) aria-current="page" @endif><i class="ph ph-users-three" aria-hidden="true"></i> Cari Talenta</a>
                <a href="{{ route('jobs.index') }}" @if (request()->routeIs('jobs.index', 'jobs.show')) aria-current="page" @endif><i class="ph ph-briefcase" aria-hidden="true"></i> Lowongan</a>
                <a href="{{ route('companies') }}" @if (request()->routeIs('companies')) aria-current="page" @endif><i class="ph ph-buildings" aria-hidden="true"></i> Untuk Perusahaan</a>
                <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif><i class="ph ph-info" aria-hidden="true"></i> Tentang Kami</a>
                <a href="{{ route('contact') }}" @if (request()->routeIs('contact')) aria-current="page" @endif><i class="ph ph-chat-circle-text" aria-hidden="true"></i> Kontak</a>
            </nav>
            @auth
                <div class="mnav-links mnav-acct">
                    <span class="lbl">Akun saya</span>
                    @if ($me->isTalent())
                        <a href="{{ $me->talent ? route('talents.show', $me->talent) : route('profile.edit') }}"><i class="ph ph-user" aria-hidden="true"></i> Profil saya</a>
                        <a href="{{ route('talent.offers') }}"><i class="ph ph-tray" aria-hidden="true"></i> Tawaran masuk @if ($me->pendingOfferCount())<span class="count">{{ $me->pendingOfferCount() }}</span>@endif</a>
                        <a href="{{ route('talent.applications') }}"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i> Lamaran saya</a>
                    @elseif ($me->isCompany())
                        <a href="{{ route('company.jobs') }}"><i class="ph ph-briefcase" aria-hidden="true"></i> Lowongan saya</a>
                        <a href="{{ route('company.offers') }}"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i> Tawaran terkirim</a>
                        <a href="{{ route('jobs.posting.create') }}"><i class="ph ph-plus" aria-hidden="true"></i> Pasang lowongan</a>
                        <a href="{{ route('company.profile') }}"><i class="ph ph-identification-card" aria-hidden="true"></i> Profil perusahaan @unless ($me->isVerifiedCompany())<span class="count warn">!</span>@endunless</a>
                    @elseif ($me->isAdmin())
                        <a href="/admin"><i class="ph ph-gauge" aria-hidden="true"></i> Panel admin</a>
                    @endif
                    <a href="{{ route('account') }}" @if (request()->routeIs('account')) aria-current="page" @endif><i class="ph ph-gear-six" aria-hidden="true"></i> Pengaturan akun</a>
                </div>
            @endauth
            <div class="mnav-foot">
                <button type="button" class="btn btn-line" @click="$store.theme.toggle()">
                    <i class="ph" :class="$store.theme.dark ? 'ph-sun' : 'ph-moon'" aria-hidden="true"></i>
                    <span x-text="$store.theme.dark ? 'Mode terang' : 'Mode gelap'">Mode gelap</span>
                </button>
                @auth
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-ink" type="submit" style="width:100%"><i class="ph ph-sign-out" aria-hidden="true"></i> Keluar</button></form>
                @else
                    <button type="button" class="btn btn-ink" @click="menu = false; $store.auth.show({ tab: 'masuk' })">Masuk</button>
                @endauth
            </div>
        </div>
    </div>
    </div>

    @if (session('status'))
        <div class="wrap"><div class="ok-banner" role="status">{{ session('status') }}</div></div>
    @endif

    <main id="top">
        {{ $slot }}
    </main>

    <footer class="site">
        <div class="hz" aria-hidden="true"></div>
        <div class="wrap in">
            <div>
                <a href="{{ route('home') }}" aria-label="Kabelota, ke beranda"><img src="/images/brand/kabelota-warna.webp" alt="Kabelota" width="168" height="36"></a>
                <p>Kebaikan untuk bersama. Platform tenaga ahli Teknik Sipil, dimulai dari Universitas Tadulako.</p>
            </div>
            <div><h4>Platform</h4><ul>
                <li><a href="{{ route('talents.index') }}">Cari Talenta</a></li>
                <li><a href="{{ route('jobs.index') }}">Lowongan</a></li>
                <li><a href="{{ route('companies') }}">Untuk Perusahaan</a></li>
                <li><a href="{{ route('about') }}">Tentang Kami</a></li>
            </ul></div>
            <div><h4>Bantuan</h4><ul>
                <li><a href="{{ route('home') }}#faq-h">FAQ</a></li>
                <li><a href="{{ route('privacy') }}">Kebijakan Privasi</a></li>
                <li><a href="{{ route('terms') }}">Syarat Penggunaan</a></li>
            </ul></div>
            <div id="kontak"><h4>Kontak</h4><ul>
                <li>Sekretariat HMTS, Fakultas Teknik Universitas Tadulako, Palu</li>
                <li><a href="{{ config('kabelota.contact.instagram') }}" target="_blank" rel="noopener">Instagram {{ config('kabelota.contact.instagram_handle') }}</a></li>
                <li><a href="{{ route('contact') }}">Kirim pesan</a></li>
            </ul></div>
        </div>
        <div class="wrap credits">
            <div class="credit">
                <span class="cap">Diselenggarakan oleh</span>
                <div class="logos">
                    <img src="/images/brand/untad.webp" alt="Universitas Tadulako" width="44" height="44">
                    <img src="/images/brand/hmts.webp" alt="HMTS Universitas Tadulako" width="44" height="44">
                    <span>Himpunan Mahasiswa Teknik Sipil<br>Universitas Tadulako</span>
                </div>
            </div>
            <div class="credit">
                <span class="cap">Powered by</span>
                <div class="logos">
                    <img src="/images/brand/rinoya.webp" alt="RINOYA UNTAD" width="44" height="44">
                    <span>RINOYA UNTAD</span>
                </div>
            </div>
        </div>
        <div class="wrap base"><span>© {{ date('Y') }} Kabelota · Palu, Sulawesi Tengah</span><span>Foto sementara dari Unsplash</span></div>
    </footer>

    <button type="button" class="to-top" x-data="{ show: false }" @scroll.window.throttle.150ms="show = window.scrollY > 700"
        x-show="show" x-cloak x-transition.opacity @click="window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })"
        aria-label="Kembali ke atas" title="Kembali ke atas"><i class="ph ph-arrow-up" aria-hidden="true"></i></button>

    @if ($me?->isTalent())
    {{-- Apply confirmation popup (opened from job cards via $store.apply) --}}
    <div class="modal-bg" x-show="$store.apply.job" x-cloak x-transition.opacity @click.self="$store.apply.job = null" @keydown.escape.window="$store.apply.job = null">
        <form class="sheet" method="post" :action="$store.apply.job?.url" role="dialog" aria-modal="true" aria-labelledby="apply-h" x-trap.noscroll="$store.apply.job">
            @csrf
            <header>
                <span class="lbl">Lamar lowongan</span>
                <h2 id="apply-h" x-text="$store.apply.job?.title"></h2>
                <p class="demo-note" x-text="$store.apply.job?.company"></p>
            </header>
            <div class="content">
                @if ($me->talent)
                    <p class="privacy">Perusahaan akan melihat profil, SKK, dan CV Anda. Nomor HP dan email baru terbuka kalau lamaran Anda diterima.</p>
                    <div class="fld"><label for="apply-msg">Pesan singkat <span class="demo-note">(opsional)</span></label>
                        <textarea class="box" id="apply-msg" name="message" rows="3" maxlength="600" placeholder="mis. Saya berdomisili di Palu dan siap ditempatkan di lokasi proyek."></textarea></div>
                @else
                    <p class="privacy">Anda belum punya profil. Buat profil dulu supaya perusahaan bisa menilai lamaran Anda.</p>
                @endif
            </div>
            <footer>
                <button type="button" class="btn btn-line" @click="$store.apply.job = null">Batal</button>
                @if ($me->talent)
                    <button type="submit" class="btn btn-accent" data-umami-event="kirim-lamaran">Kirim Lamaran</button>
                @else
                    <a class="btn btn-accent" href="{{ route('profile.edit') }}">Buat Profil</a>
                @endif
            </footer>
        </form>
    </div>
    @endif

    {{-- Login / daftar popup --}}
    <div class="modal-bg" x-show="$store.auth.open" x-cloak x-transition.opacity @click.self="$store.auth.open = false" @keydown.escape.window="$store.auth.open = false">
        <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="auth-h" x-trap.noscroll="$store.auth.open">
            <header>
                <h2 id="auth-h" x-text="$store.auth.tab === 'masuk' ? 'Masuk ke Kabelota' : 'Daftar di Kabelota'">Masuk ke Kabelota</h2>
                <p x-show="$store.auth.reason" x-text="$store.auth.reason" style="font-size:14px;color:var(--muted)"></p>
                <div class="tabs" role="tablist" style="margin-top:8px">
                    <button type="button" class="tab" role="tab" :aria-selected="$store.auth.tab === 'masuk'" @click="$store.auth.tab = 'masuk'">Masuk</button>
                    <button type="button" class="tab" role="tab" :aria-selected="$store.auth.tab === 'daftar'" @click="$store.auth.tab = 'daftar'">Daftar</button>
                </div>
            </header>

            <form class="content" x-show="$store.auth.tab === 'masuk'" method="post" action="{{ route('login.store') }}" novalidate>
                @csrf
                <div class="fld"><label for="a-email">Email</label>
                    <input class="box @error('email', 'login') is-err @enderror" id="a-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email', 'login')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="a-pass">Kata sandi</label>
                    <input class="box @error('password', 'login') is-err @enderror" id="a-pass" name="password" type="password" autocomplete="current-password" required>
                    @error('password', 'login')<span class="err">{{ $message }}</span>@enderror</div>
                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
                    <label class="consent"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
                    <a class="textlink" href="{{ route('password.request') }}" style="font-size:13.5px">Lupa kata sandi?</a>
                </div>
                <button type="submit" class="btn btn-ink">Masuk</button>
                @if (config('kabelota.demo_mode'))
                    <p class="demo-note">Untuk demo, coba tanpa akun sebagai:</p>
                    <div class="two">
                        <button class="btn btn-accent" type="submit" form="demo-hrd" style="width:100%">HRD Perusahaan</button>
                        <button class="btn btn-line" type="submit" form="demo-talenta" style="width:100%">Talenta</button>
                    </div>
                @endif
            </form>

            <form class="content" x-show="$store.auth.tab === 'daftar'" x-cloak method="post" action="{{ route('register') }}" novalidate>
                <x-honeypot id="register" />
                @csrf
                <input type="hidden" name="role" :value="$store.auth.role === 'perusahaan' ? 'perusahaan' : 'talenta'">
                <div class="fld"><label for="r-name" x-text="$store.auth.role === 'perusahaan' ? 'Nama perusahaan' : 'Nama lengkap'">Nama lengkap</label>
                    <input class="box @error('name', 'register') is-err @enderror" id="r-name" name="name" value="{{ old('name') }}" autocomplete="name">
                    @error('name', 'register')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="r-email">Email</label>
                    <input class="box @error('email', 'register') is-err @enderror" id="r-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                    @error('email', 'register')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="r-pass">Kata sandi</label>
                    <input class="box @error('password', 'register') is-err @enderror" id="r-pass" name="password" type="password" autocomplete="new-password">
                    <span class="demo-note">Minimal 8 karakter.</span>
                    @error('password', 'register')<span class="err">{{ $message }}</span>@enderror</div>
                <p class="demo-note" x-show="$store.auth.role !== 'perusahaan'">Status Alumni atau Mahasiswa dipilih saat membuat profil, jadi bisa diubah setelah Anda lulus.</p>
                <p class="demo-note" x-show="$store.auth.role === 'perusahaan'">Setelah mendaftar, lengkapi profil perusahaan dan unggah NIB atau SBU. Admin mengeceknya sebelum akun bisa merekrut.</p>
                <label class="consent"><input type="checkbox" name="consent" value="1"> Saya setuju dengan <a class="textlink" href="{{ route('terms') }}" target="_blank">Syarat Penggunaan</a> dan data saya diproses sesuai <a class="textlink" href="{{ route('privacy') }}" target="_blank">Kebijakan Privasi</a> (UU No. 27 Tahun 2022).</label>
                @error('consent', 'register')<span class="err">{{ $message }}</span>@enderror
                <button type="submit" class="btn btn-accent" data-umami-event="daftar-akun">Buat Akun</button>
                <p class="demo-note">
                    <button type="button" class="textlink" style="background:none;border:0;padding:0;cursor:pointer;color:var(--ink)" @click="$store.auth.role = $store.auth.role === 'perusahaan' ? 'talenta' : 'perusahaan'"
                        x-text="$store.auth.role === 'perusahaan' ? 'Saya alumni atau mahasiswa' : 'Mewakili perusahaan? Daftar sebagai perusahaan'">Mewakili perusahaan? Daftar sebagai perusahaan</button>
                </p>
            </form>
        </div>
    </div>
    @if (config('kabelota.demo_mode'))
        <form id="demo-hrd" method="post" action="{{ route('demo.login') }}" hidden>@csrf<input type="hidden" name="role" value="perusahaan"></form>
        <form id="demo-talenta" method="post" action="{{ route('demo.login') }}" hidden>@csrf<input type="hidden" name="role" value="talenta"></form>
    @endif
</body>
</html>
