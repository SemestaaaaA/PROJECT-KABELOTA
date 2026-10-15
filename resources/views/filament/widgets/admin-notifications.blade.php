<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-bell" heading="Notifikasi" wire:poll.60s>
        <x-slot name="afterHeader">
            @if ($unread)
                <x-filament::badge color="warning" class="whitespace-nowrap">{{ $unread }} baru</x-filament::badge>
            @endif
        </x-slot>
        @if ($items->isEmpty())
            <div class="kb-empty"><x-filament::icon icon="heroicon-o-bell-slash" class="h-8 w-8" /><b>Belum ada notifikasi</b><span>Kejadian penting akan muncul di sini.</span></div>
        @else
            <ul class="kb-list">
                @foreach ($items as $n)
                    <li @class(['kb-list-item', 'is-unread' => ! $n->read_at]) wire:key="notif-{{ $n->id }}">
                        @unless ($n->read_at)
                            <span class="kb-dot" role="img" aria-label="Belum dibaca"></span>
                        @endunless
                        <div class="min-w-0 flex-1">
                            {{-- Stored escaped by AdminAlerts; rendered the same way Filament's bell renders it. --}}
                            <p class="text-sm font-semibold">
                                <button type="button" wire:click="open('{{ $n->id }}')" class="text-start hover:underline">{!! str($n->data['title'] ?? '')->sanitizeHtml() !!}</button>
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{!! str($n->data['body'] ?? '')->sanitizeHtml() !!}</p>
                            <p class="kb-mono text-xs text-gray-500 dark:text-gray-400">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($unread)
            <x-slot name="footer">
                <x-filament::link tag="button" size="sm" color="gray" wire:click="markAllRead" icon="heroicon-o-check">Tandai semua dibaca</x-filament::link>
            </x-slot>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
