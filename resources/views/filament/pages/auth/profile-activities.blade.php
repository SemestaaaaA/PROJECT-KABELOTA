@if ($activities->isEmpty())
    <div class="kb-empty"><x-filament::icon icon="heroicon-o-clock" class="h-8 w-8" /><b>Belum ada aktivitas</b><span>Verifikasi dan penolakan yang Anda lakukan akan tercatat di sini.</span></div>
@else
    <ul class="kb-list">
        @foreach ($activities as $activity)
            <li class="kb-list-item">
                <div class="min-w-0 flex-1">
                    <p class="text-sm">
                        <x-filament::badge :color="str_ends_with($activity->action, '_rejected') ? 'danger' : 'success'" size="sm" class="me-1 inline-flex align-middle">{{ $activity->actionLabel() }}</x-filament::badge>
                        {{ $activity->description }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><span class="kb-mono" title="{{ $activity->created_at?->translatedFormat('j M Y H:i') }}">{{ $activity->created_at?->diffForHumans() }}</span></p>
                </div>
            </li>
        @endforeach
    </ul>
@endif
<div class="mt-3">
    <x-filament::link :href="$url" size="sm" icon="heroicon-o-arrow-right" icon-position="after">Lihat semua</x-filament::link>
</div>
