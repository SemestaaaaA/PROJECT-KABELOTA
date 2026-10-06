<x-layouts.app title="Kontak · Kabelota">
<div class="wrap">
    <div class="page-h">
        <h1>Kontak</h1>
        <p>Pertanyaan, kerja sama, atau kendala akun. Admin membalas di hari kerja.</p>
    </div>

    <div class="split" style="margin-top:28px">
        <div class="channels">
            <a class="channel" href="{{ config('kabelota.contact.instagram') }}" target="_blank" rel="noopener">
                <i class="ph ph-instagram-logo" aria-hidden="true"></i>
                <span><b>Instagram</b>{{ config('kabelota.contact.instagram_handle') }}</span>
            </a>
            <div class="channel">
                <i class="ph ph-whatsapp-logo" aria-hidden="true"></i>
                <span><b>WhatsApp Bisnis</b><span class="mono">{{ config('kabelota.contact.whatsapp') }}</span> <em style="font-style:normal">(placeholder)</em></span>
            </div>
            <div class="channel">
                <i class="ph ph-envelope-simple" aria-hidden="true"></i>
                <span><b>Email</b><span class="mono">{{ config('kabelota.contact.email') }}</span> <em style="font-style:normal">(placeholder)</em></span>
            </div>
            <div class="channel">
                <i class="ph ph-map-pin" aria-hidden="true"></i>
                <span><b>Sekretariat</b>HMTS, Fakultas Teknik Universitas Tadulako, Palu</span>
            </div>
        </div>

        <form class="formcard" method="post" action="{{ route('contact.store') }}" novalidate>
            <x-honeypot id="contact" />
            @csrf
            <h2 style="font:700 20px var(--f-body)">Kirim pesan</h2>
            @if (session('contact_sent'))
                <div class="ok-banner" role="status" style="margin-top:0">Pesan terkirim. Admin akan membalas ke email Anda.</div>
            @endif
            <div class="two">
                <div class="fld"><label for="c-name">Nama</label>
                    <input class="box @error('name') is-err @enderror" id="c-name" name="name" value="{{ old('name') }}" autocomplete="name">
                    @error('name')<span class="err">{{ $message }}</span>@enderror</div>
                <div class="fld"><label for="c-email">Email</label>
                    <input class="box @error('email') is-err @enderror" id="c-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                    @error('email')<span class="err">{{ $message }}</span>@enderror</div>
            </div>
            <div class="fld"><label for="c-topic">Topik</label>
                <select class="box" id="c-topic" name="topic">
                    @foreach ($topics as $t)<option @selected(old('topic') === $t)>{{ $t }}</option>@endforeach
                </select></div>
            <div class="fld"><label for="c-msg">Pesan</label>
                <textarea class="box @error('message') is-err @enderror" id="c-msg" name="message" rows="5">{{ old('message') }}</textarea>
                @error('message')<span class="err">{{ $message }}</span>@enderror</div>
            <button class="btn btn-accent" type="submit" style="justify-self:start">Kirim Pesan</button>
        </form>
    </div>
</div>
</x-layouts.app>
