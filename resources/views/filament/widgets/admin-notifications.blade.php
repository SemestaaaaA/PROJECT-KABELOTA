<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-bell" heading="Notifikasi" wire:poll.30s>
        <x-slot name="afterHeader">
            @if ($unread)
                <x-filament::button size="xs" color="gray" wire:click="markAllRead">Tandai semua dibaca ({{ $unread }})</x-filament::button>
            @endif
        </x-slot>
        @if ($items->isEmpty())
            <div class="kb-empty"><x-filament::icon icon="heroicon-o-bell-slash" class="h-8 w-8" /><b>Belum ada notifikasi</b><span>Kejadian penting akan muncul di sini.</span></div>
        @else
            <ul class="kb-list">
                @foreach ($items as $n)
                    @php($url = collect($n->data['actions'] ?? [])->first()['url'] ?? null)
                    <li @class(['kb-list-item', 'is-unread' => ! $n->read_at])>
                        @unless ($n->read_at)
                            <span class="kb-dot" role="img" aria-label="Belum dibaca"></span>
                        @endunless
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">
                                @if ($url)
                                    <a href="{{ $url }}" class="hover:underline">{{ $n->data['title'] ?? '' }}</a>
                                @else
                                    {{ $n->data['title'] ?? '' }}
                                @endif
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $n->data['body'] ?? '' }}</p>
                            <p class="text-xs text-gray-400">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
