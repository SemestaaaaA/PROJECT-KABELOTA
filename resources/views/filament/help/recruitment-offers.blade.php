{{-- "Apa ini?" modal on the recruitment offers list. Status meanings live in RecruitmentOfferResource::STATUS_HELP. --}}
<div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
    <p>
        Tawaran rekrut adalah perusahaan yang lebih dulu menghubungi talenta dari halaman <b>Cari Talenta</b>.
        Berbeda dengan Lamaran, ketika talenta yang melamar lowongan.
    </p>

    <div>
        <p class="font-semibold text-gray-950 dark:text-white">Urutan status</p>
        <ol class="mt-2 space-y-2">
            @foreach ($help as $status => $meaning)
                <li class="flex gap-3">
                    <span class="w-32 shrink-0 font-medium text-gray-950 dark:text-white">{{ $statuses[$status] }}</span>
                    <span class="text-gray-600 dark:text-gray-300">{{ $meaning }}</span>
                </li>
            @endforeach
        </ol>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Alur utama: Menunggu jawaban, lalu Tertarik, lalu Tawaran kerja, lalu Diambil. Ditolak, Ditutup, dan Kedaluwarsa adalah akhir lain.
        </p>
    </div>

    <p class="rounded-lg bg-gray-50 p-3 text-gray-600 dark:bg-white/5 dark:text-gray-300">
        Admin hanya memantau. Jawaban datang dari talenta dan perusahaan.
    </p>
</div>
