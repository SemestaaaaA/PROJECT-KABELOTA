@props(['items'])
{{-- Sub-navigation for account pages: [[label, route, badge?], ...] --}}
<nav class="page-tabs" aria-label="Menu akun">
    @foreach ($items as $item)
        <a href="{{ route($item[1]) }}" @if (request()->routeIs($item[1])) aria-current="page" @endif>{{ $item[0] }}@if (! empty($item[2]))<span class="count">{{ $item[2] }}</span>@endif</a>
    @endforeach
</nav>
