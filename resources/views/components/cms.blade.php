{{--
    Mode CMS text: the x-cms component takes key, tag and class attributes, and the default text as its slot.
    The slot is the default. An admin override (sanitized when it was saved) replaces it. Register new keys in
    App\Support\SiteText::KEYS. In edit mode the element carries what resources/js/cms.js needs.
--}}
@props(['key', 'tag' => 'p'])
@php
    $tag = in_array($tag, ['h1', 'h2', 'h3', 'p', 'span', 'b', 'blockquote'], true) ? $tag : 'p';
    $override = \App\Support\SiteText::get($key);
    if (\App\Support\SiteText::editing()) {
        $attributes = $attributes->class('cms-editable')->merge([
            'data-cms-key' => $key,
            'data-cms-default' => trim((string) $slot),
            'data-cms-version' => \App\Support\SiteText::version($key),
        ]);
    }
@endphp
<{{ $tag }} {{ $attributes }}>@if ($override !== null){!! $override !!}@else{{ $slot }}@endif</{{ $tag }}>
