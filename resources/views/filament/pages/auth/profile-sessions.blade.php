{{-- Rows of the sessions table for this admin; payload is never read. --}}
@if ($sessions->isEmpty())
    <div class="kb-empty"><x-filament::icon icon="heroicon-o-computer-desktop" class="h-8 w-8" /><b>Belum ada sesi tercatat</b><span>Sesi muncul di sini setelah Anda masuk dari sebuah perangkat.</span></div>
@else
    <ul class="kb-list">
        @foreach ($sessions as $session)
            <li class="kb-list-item">
                <x-filament::icon :icon="$session->icon" class="h-5 w-5 text-gray-400" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold">
                        {{ $session->device }}
                        @if ($session->is_current)
                            <x-filament::badge color="success" size="sm" class="ms-1 inline-flex align-middle">Perangkat ini</x-filament::badge>
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        <span class="kb-mono">{{ $session->ip_address ?: 'IP tidak diketahui' }}</span> · Aktif {{ $session->last_active }}
                    </p>
                </div>
            </li>
        @endforeach
    </ul>
@endif
