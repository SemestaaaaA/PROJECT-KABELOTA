@props(['items' => config('kabelota.faq')])
<div class="faq-list">
    @foreach ($items as [$q, $a])
        <details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
    @endforeach
</div>
