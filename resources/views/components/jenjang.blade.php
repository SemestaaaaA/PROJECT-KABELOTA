@props(['level'])
@php($short = [4 => 'Teknisi', 5 => 'Teknisi', 6 => 'Teknisi', 7 => 'Muda', 8 => 'Madya', 9 => 'Utama'][(int) $level] ?? '')
<span class="jen" title="Jenjang {{ $level }}, {{ config('kabelota.jenjang')[(int) $level] ?? '' }}"><span>{{ $level }}</span><span>{{ $short }}</span></span>
