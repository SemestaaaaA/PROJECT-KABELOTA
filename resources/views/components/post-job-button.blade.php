@if (auth()->user()?->isCompany())
    <a {{ $attributes }} href="{{ route('jobs.posting.create') }}">Pasang Lowongan</a>
@else
    <button type="button" {{ $attributes }} @click="$store.auth.show({ tab: 'daftar', role: 'perusahaan', reason: 'Daftarkan perusahaan Anda untuk memasang lowongan.' })">Pasang Lowongan</button>
@endif
