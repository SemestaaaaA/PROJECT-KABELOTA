<x-filament-widgets::widget>
    <x-filament::section heading="Pengunjung situs (Umami)" description="Kunjungan, halaman populer, sumber trafik, dan perangkat. Tanpa cookie dan tanpa data pribadi.">
        <x-slot name="afterHeader">
            <x-filament::link :href="config('kabelota.analytics.share_url')" target="_blank" icon="heroicon-o-arrow-top-right-on-square">
                Buka di Umami
            </x-filament::link>
        </x-slot>
        <iframe src="{{ config('kabelota.analytics.share_url') }}" title="Statistik pengunjung Kabelota" loading="lazy"
            style="width:100%;height:720px;border:0;border-radius:8px;background:transparent"></iframe>
    </x-filament::section>
</x-filament-widgets::widget>
