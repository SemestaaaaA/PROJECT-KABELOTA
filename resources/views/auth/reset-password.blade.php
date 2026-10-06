<x-layouts.app title="Kata Sandi Baru · Kabelota">
<div class="wrap" style="max-width:560px">
    <div class="page-h">
        <h1>Kata sandi baru</h1>
        <p>Minimal 8 karakter. Setelah disimpan, masuk dengan kata sandi baru.</p>
    </div>
    <form class="formcard" method="post" action="{{ route('password.update') }}" style="margin-top:24px" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="fld"><label for="r-email">Email</label>
            <input class="box @error('email') is-err @enderror" id="r-email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
            @error('email')<span class="err">{{ $message }}</span>@enderror</div>
        <div class="fld"><label for="r-password">Kata sandi baru</label>
            <input class="box @error('password') is-err @enderror" id="r-password" name="password" type="password" autocomplete="new-password" required>
            @error('password')<span class="err">{{ $message }}</span>@enderror</div>
        <div class="fld"><label for="r-password2">Ulangi kata sandi</label>
            <input class="box" id="r-password2" name="password_confirmation" type="password" autocomplete="new-password" required></div>
        <button class="btn btn-accent" type="submit" style="justify-self:start">Simpan Kata Sandi</button>
    </form>
</div>
</x-layouts.app>
