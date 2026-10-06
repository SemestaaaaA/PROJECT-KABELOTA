<x-layouts.app title="Lupa Kata Sandi · Kabelota">
<div class="wrap" style="max-width:560px">
    <div class="page-h">
        <h1>Lupa kata sandi</h1>
        <p>Masukkan email akun Anda. Kami kirim link untuk membuat kata sandi baru.</p>
    </div>
    <form class="formcard" method="post" action="{{ route('password.email') }}" style="margin-top:24px" novalidate>
        @csrf
        <div class="fld"><label for="f-email">Email</label>
            <input class="box @error('email') is-err @enderror" id="f-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            @error('email')<span class="err">{{ $message }}</span>@enderror</div>
        <button class="btn btn-ink" type="submit" style="justify-self:start">Kirim Link</button>
        @if (app()->isLocal())<p class="demo-note">Mode lokal: link ada di <span class="mono">storage/logs/laravel.log</span>.</p>@endif
    </form>
</div>
</x-layouts.app>
