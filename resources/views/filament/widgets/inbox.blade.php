<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-envelope" heading="Pesan masuk">
        <x-slot name="afterHeader">
            @if ($unread)
                <x-filament::badge color="warning">{{ $unread }} belum dibaca</x-filament::badge>
            @endif
        </x-slot>
        @if ($items->isEmpty())
            <div class="kb-empty"><x-filament::icon icon="heroicon-o-inbox" class="h-8 w-8" /><b>Belum ada pesan</b><span>Pesan dari halaman Kontak akan muncul di sini.</span></div>
        @else
            <ul class="kb-list">
                @foreach ($items as $m)
                    <li @class(['kb-list-item', 'is-unread' => ! $m->read_at])>
                        @unless ($m->read_at)
                            <span class="kb-dot" role="img" aria-label="Belum dibaca"></span>
                        @endunless
                        <div class="min-w-0 flex-1">
                            <p @class(['text-sm', 'font-semibold' => ! $m->read_at])>{{ $m->name }}</p>
                            <p class="text-xs text-gray-400">{{ $m->topic }} · {{ $m->created_at->diffForHumans() }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($m->message, 70) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-slot name="footer">
            <x-filament::link :href="$url" icon="heroicon-o-arrow-right" icon-position="after">Buka semua pesan</x-filament::link>
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
