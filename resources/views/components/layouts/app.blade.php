@php($demoRole = session('demo_role'))
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
<body x-data @if (session('auth_required')) x-init="$store.auth.show({ tab: 'masuk', reason: '{{ session('auth_required') === 'perusahaan' ? 'Masuk sebagai perusahaan terverifikasi untuk mengajukan rekrut.' : 'Masuk sebagai talenta untuk membuat profil.' }}' })" @endif>
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
            @if ($demoRole)
                @if ($demoRole === 'talenta')
                    <a class="in menu-me" href="{{ route('profile.edit') }}">Profil Saya</a>
                @else
                    <span class="who">HRD demo</span>
                @endif
                <form method="post" action="{{ route('demo.logout') }}">@csrf<button class="btn btn-line btn-sm" type="submit">Keluar</button></form>
            @else
                <button type="button" class="in" @click="$store.auth.show({ tab: 'masuk' })">Masuk</button>
                <button type="button" class="btn btn-accent btn-sm" @click="$store.auth.show({ tab: 'daftar' })">Daftar</button>
            @endif
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

            <div class="content" x-show="$store.auth.tab === 'masuk'">
                <div class="fld"><label for="a-email">Email</label><input class="box" id="a-email" type="email" autocomplete="email"></div>
                <div class="fld"><label for="a-pass">Kata sandi</label><input class="box" id="a-pass" type="password" autocomplete="current-password"></div>
                <button type="button" class="btn btn-ink" disabled>Masuk</button>
                <p class="demo-note">Login asli aktif saat launch. Untuk demo, coba sebagai:</p>
                <div class="two">
                    <form method="post" action="{{ route('demo.login') }}">@csrf<input type="hidden" name="role" value="perusahaan"><button class="btn btn-accent" type="submit" style="width:100%">HRD Perusahaan</button></form>
                    <form method="post" action="{{ route('demo.login') }}">@csrf<input type="hidden" name="role" value="talenta"><button class="btn btn-line" type="submit" style="width:100%">Talenta</button></form>
                </div>
            </div>

            <div class="content" x-show="$store.auth.tab === 'daftar'" x-cloak>
                <div class="fld"><label for="r-name" x-text="$store.auth.role === 'perusahaan' ? 'Nama perusahaan' : 'Nama lengkap'">Nama lengkap</label><input class="box" id="r-name" autocomplete="name"></div>
                <div class="fld"><label for="r-email">Email</label><input class="box" id="r-email" type="email" autocomplete="email"></div>
                <div class="fld"><label for="r-pass">Kata sandi</label><input class="box" id="r-pass" type="password" autocomplete="new-password"></div>
                <p class="demo-note" x-show="$store.auth.role !== 'perusahaan'">Status Alumni atau Mahasiswa dipilih saat membuat profil, jadi bisa diubah setelah Anda lulus.</p>
                <p class="demo-note" x-show="$store.auth.role === 'perusahaan'">Perusahaan mengunggah NIB atau SBU setelah mendaftar. Admin mengeceknya sebelum akun aktif.</p>
                <label class="consent"><input type="checkbox"> Saya setuju data saya diproses sesuai Kebijakan Privasi Kabelota (UU No. 27 Tahun 2022).</label>
                <button type="button" class="btn btn-accent" disabled>Buat Akun</button>
                <p class="demo-note">
                    <button type="button" class="textlink" style="background:none;border:0;padding:0;cursor:pointer;color:var(--ink)" @click="$store.auth.role = $store.auth.role === 'perusahaan' ? 'talenta' : 'perusahaan'"
                        x-text="$store.auth.role === 'perusahaan' ? 'Saya alumni atau mahasiswa' : 'Mewakili perusahaan? Daftar sebagai perusahaan'"></button>
                </p>
                <p class="demo-note">Pendaftaran dibuka saat launch. Untuk mencoba fitur, pilih tab Masuk lalu "coba sebagai".</p>
            </div>
        </div>
    </div>
</body>
</html>
