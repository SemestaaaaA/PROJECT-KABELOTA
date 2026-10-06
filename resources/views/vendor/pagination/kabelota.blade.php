@if ($paginator->hasPages())
<nav class="pager" aria-label="Halaman">
    <span>Menampilkan {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} dari {{ $paginator->total() }}</span>
    <div class="pages">
        <a href="{{ $paginator->previousPageUrl() }}" @class(['off' => $paginator->onFirstPage()]) aria-label="Sebelumnya">&lsaquo;</a>
        @foreach ($elements as $element)
            @if (is_string($element))<span class="cur" style="background:none;color:var(--muted);border-color:transparent">…</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="cur" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        <a href="{{ $paginator->nextPageUrl() }}" @class(['off' => ! $paginator->hasMorePages()]) aria-label="Berikutnya">&rsaquo;</a>
    </div>
</nav>
@endif
