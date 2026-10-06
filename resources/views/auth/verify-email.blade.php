<x-layouts.app title="Verifikasi Email · Kabelota">
<div class="wrap" style="max-width:640px">
    <div class="page-h">
        <h1>Cek email Anda</h1>
        <p>Kami mengirim link verifikasi ke <b>{{ auth()->user()->email }}</b>. Klik link itu untuk mengaktifkan akun, lalu Anda bisa {{ auth()->user()->isCompany() ? 'melengkapi profil perusahaan' : 'membuat profil' }}.</p>
    </div>
    <div class="formcard" style="margin-top:24px">
        <p class="demo-note" style="font-size:14px">Tidak ada di kotak masuk? Cek folder Spam atau Promosi. Link berlaku 60 menit.</p>
        <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn btn-ink" type="submit">Kirim Ulang Link</button></form>
        @if (app()->isLocal())
            <p class="demo-note">Mode lokal: email tidak benar-benar dikirim. Link ada di <span class="mono">storage/logs/laravel.log</span>.</p>
        @endif
    </div>
</div>
</x-layouts.app>
