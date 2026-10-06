@props(['title', 'updated'])
<x-layouts.app :title="$title.' · Kabelota'">
<div class="wrap" style="max-width:820px">
    <div class="page-h">
        <h1>{{ $title }}</h1>
        <p>Berlaku sejak {{ $updated }}.</p>
    </div>
    <div class="draft-note" style="margin-top:20px"><b>Draf.</b> Dokumen ini disusun sebagai titik awal dan perlu ditinjau ahli hukum sebelum Kabelota dibuka untuk umum. Bagian dalam [kurung siku] diisi setelah badan usaha pengelola ditetapkan.</div>
    <div class="legal">{{ $slot }}</div>
</div>
</x-layouts.app>
