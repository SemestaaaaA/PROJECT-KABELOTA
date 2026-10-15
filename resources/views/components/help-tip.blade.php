@props(['label' => 'Penjelasan'])
@php($tipId = 'tip-'.\Illuminate\Support\Str::random(8))
<span class="help-tip" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @keydown.escape="open = false" @click.outside="open = false">
    <button type="button" class="help-tip-btn" aria-label="{{ $label }}" aria-describedby="{{ $tipId }}" :aria-expanded="open.toString()"
        @click="open = true" @focus="open = true" @blur="open = false">?</button>
    <span class="help-tip-pop" id="{{ $tipId }}" role="tooltip" x-show="open" x-cloak x-transition.opacity>{{ $slot }}</span>
</span>
