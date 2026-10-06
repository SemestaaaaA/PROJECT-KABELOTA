@php($me = auth()->user())
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Kabelota · Cari Tenaga Ahli Teknik Sipil Sulawesi Tengah' }}</title>
    <meta name="description" content="{{ $description ?? 'Cari alumni dan mahasiswa Teknik Sipil UNTAD berdasarkan SKK, jenjang, dan pengalaman. Gratis untuk talenta.' }}">
    <link rel="icon" href="/favicon.ico">
    <script>
        // Apply the saved theme before paint to avoid a flash.
        try { const t = localStorage.getItem('kabelota-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data @if (session('open_auth') || $errors->login->any() || $errors->register->any()) x-init="$store.auth.show({ tab: @js($errors->register->any() ? 'daftar' : (session('open_auth') ?? 'masuk')), role: @js(old('role', 'talenta')), reason: @js(session('auth_reason', '')) })" @endif>
    <div class="demo-ribbon"><b>Mode demo.</b> Semua nama talenta, perusahaan, dan proyek adalah data contoh.</div>

    <div class="topbar" x-data="{ scrolled: false }" @scroll.window.throttle.100ms="scrolled = window.scrollY > 8" :class="scrolled && 'scrolled'">
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
            <button type="button" class="theme-switch" x-data="{ dark: false }"
                x-init="dark = document.documentElement.dataset.theme ? document.documentElement.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches"
                @click="dark = !dark; document.documentElement.dataset.theme = dark ? 'dark' : 'light'; try { localStorage.setItem('kabelota-theme', dark ? 'dark' : 'light') } catch (e) {}"
                :aria-label="dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'" :title="dark ? 'Mode terang' : 'Mode gelap'">
                <i class="ph" :class="dark ? 'ph-sun' : 'ph-moon'" aria-hidden="true"></i>
            </button>
            @auth
                @if ($me->isTalent())
                    <a class="in menu-me" href="{{ $me->talent ? route('talents.show', $me->talent) : route('profile.edit') }}">Profil Saya</a>
                @elseif ($me->isCompany())
                    <a class="in menu-me" href="{{ route('jobs.posting.create') }}">Pasang Lowongan</a>
                    <a class="in" href="{{ route('company.profile') }}">Perusahaan</a>
                @elseif ($me->isAdmin())
                    <a class="in menu-me" href="/admin">Panel Admin</a>
                @endif
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-line btn-sm" type="submit">Keluar</button></form>
            @else
                <button type="button" class="in" @click="$store.auth.show({ tab: 'masuk' })">Masuk</button>
                <button type="button" class="btn btn-accent btn-sm" @click="$store.auth.show({ tab: 'daftar' })">Daftar</button>
            @endauth
        </div>
    </header>
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
                <li><a href="#">Kebijakan Privasi</a></li>
                <li><a href="#">Syarat Penggunaan</a></li>
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
                <label class="consent"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
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
                <label class="consent"><input type="checkbox" name="consent" value="1"> Saya setuju data saya diproses sesuai Kebijakan Privasi Kabelota (UU No. 27 Tahun 2022).</label>
                @error('consent', 'register')<span class="err">{{ $message }}</span>@enderror
                <button type="submit" class="btn btn-accent">Buat Akun</button>
                <p class="demo-note">
                    <button type="button" class="textlink" style="background:none;border:0;padding:0;cursor:pointer;color:var(--ink)" @click="$store.auth.role = $store.auth.role === 'perusahaan' ? 'talenta' : 'perusahaan'"
                        x-text="$store.auth.role === 'perusahaan' ? 'Saya alumni atau mahasiswa' : 'Mewakili perusahaan? Daftar sebagai perusahaan'"></button>
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
