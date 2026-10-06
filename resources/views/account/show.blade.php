<x-layouts.app title="Pengaturan Akun · Kabelota">
<div class="wrap" style="max-width:760px">
    <div class="page-h">
        <h1>Pengaturan akun</h1>
        <p>Masuk sebagai <b>{{ $user->email }}</b> · {{ ['talenta' => 'Talenta', 'perusahaan' => 'Perusahaan', 'admin' => 'Admin'][$user->role] }}</p>
    </div>

    @if ($talent)
        <section class="formcard acct-sec" aria-labelledby="h-vis">
            <h2 id="h-vis" class="acct-h">Tampilkan profil di pencarian</h2>
            <p class="demo-note">Saat disembunyikan, profil Anda tidak muncul di Cari Talenta dan perusahaan tidak bisa mengirim tawaran baru. Tawaran dan lamaran yang sudah ada tetap tersimpan.</p>
            <form method="post" action="{{ route('account.visibility') }}" class="acct-row">
                @csrf
                <input type="hidden" name="is_visible" value="{{ $talent->is_visible ? 0 : 1 }}">
                <span @class(['badge', 'b-ok' => $talent->is_visible, 'b-off' => ! $talent->is_visible])>{{ $talent->is_visible ? 'Tampil' : 'Disembunyikan' }}</span>
                <button class="btn btn-line btn-sm" type="submit">
                    <i class="ph {{ $talent->is_visible ? 'ph-eye-slash' : 'ph-eye' }}" aria-hidden="true"></i>
                    {{ $talent->is_visible ? 'Sembunyikan profil' : 'Tampilkan lagi' }}
                </button>
            </form>
        </section>
    @endif

    <section class="formcard acct-sec" aria-labelledby="h-pass">
        <h2 id="h-pass" class="acct-h">Ganti kata sandi</h2>
        <form method="post" action="{{ route('account.password') }}" class="acct-form" novalidate>
            @csrf
            <div class="fld"><label for="a-cur">Kata sandi sekarang</label>
                <input class="box @error('current_password', 'password') is-err @enderror" id="a-cur" name="current_password" type="password" autocomplete="current-password">
                @error('current_password', 'password')<span class="err">{{ $message }}</span>@enderror</div>
            <div class="two">
                <div class="fld"><label for="a-new">Kata sandi baru</label>
                    <input class="box @error('password', 'password') is-err @enderror" id="a-new" name="password" type="password" autocomplete="new-password" minlength="8">
                    @error('password', 'password')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="a-new2">Ulangi kata sandi baru</label>
                    <input class="box" id="a-new2" name="password_confirmation" type="password" autocomplete="new-password"></div>
            </div>
            <button class="btn btn-ink" type="submit">Simpan kata sandi</button>
        </form>
    </section>

    <section class="formcard acct-sec" aria-labelledby="h-mail">
        <h2 id="h-mail" class="acct-h">Ganti email</h2>
        <p class="demo-note">Kami akan mengirim link verifikasi ke email baru. Sampai diverifikasi, fitur akun terkunci.</p>
        <form method="post" action="{{ route('account.email') }}" class="acct-form" novalidate>
            @csrf
            <div class="two">
                <div class="fld"><label for="a-mail">Email baru</label>
                    <input class="box @error('email', 'email') is-err @enderror" id="a-mail" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email">
                    @error('email', 'email')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="a-mailpass">Kata sandi</label>
                    <input class="box @error('current_password', 'email') is-err @enderror" id="a-mailpass" name="current_password" type="password" autocomplete="current-password">
                    @error('current_password', 'email')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <button class="btn btn-ink" type="submit">Ganti email</button>
        </form>
    </section>

    @unless ($user->isAdmin())
        <section class="formcard acct-sec acct-danger" aria-labelledby="h-del" x-data="{ open: {{ $errors->delete->any() ? 'true' : 'false' }} }">
            <h2 id="h-del" class="acct-h">Hapus akun</h2>
            <p class="demo-note">
                @if ($user->isCompany())
                    Profil perusahaan, semua lowongan, bukti transfer, dan dokumen legalitas ikut terhapus permanen.
                @else
                    Profil, foto, CV, scan SKK, transkrip, tawaran, dan lamaran Anda ikut terhapus permanen.
                @endif
                Tindakan ini tidak bisa dibatalkan.
            </p>
            <button class="btn btn-line btn-sm" type="button" x-show="!open" @click="open = true"><i class="ph ph-trash" aria-hidden="true"></i> Saya ingin menghapus akun</button>
            <form method="post" action="{{ route('account.destroy') }}" class="acct-form" x-show="open" x-cloak novalidate>
                @csrf
                @method('delete')
                <div class="two">
                    <div class="fld"><label for="a-delpass">Kata sandi</label>
                        <input class="box @error('current_password', 'delete') is-err @enderror" id="a-delpass" name="current_password" type="password" autocomplete="current-password">
                        @error('current_password', 'delete')<span class="err">{{ $message }}</span>@enderror</div>
                    <div class="fld"><label for="a-confirm">Ketik <b>HAPUS</b> untuk konfirmasi</label>
                        <input class="box mono @error('confirm', 'delete') is-err @enderror" id="a-confirm" name="confirm" autocomplete="off">
                        @error('confirm', 'delete')<span class="err">{{ $message }}</span>@enderror</div>
                </div>
                <div class="acct-row">
                    <button class="btn btn-danger" type="submit">Hapus akun permanen</button>
                    <button class="btn btn-line" type="button" @click="open = false">Batal</button>
                </div>
            </form>
        </section>
    @endunless
</div>
</x-layouts.app>
