@props(['status'])
<span {{ $attributes->class(['badge', $status->badgeClass()]) }}>{{ $status->label() }}</span>
