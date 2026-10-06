{{-- Standalone layout: no database or session calls, so it still renders when the app itself is failing. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('code') · Kabelota</title>
    <link rel="icon" href="/favicon.ico">
    <script>
        try { const t = localStorage.getItem('kabelota-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="err-page wrap">
        <a class="logo" href="/" aria-label="Kabelota, ke beranda">
            <img class="for-light" src="/images/brand/kabelota-hitam.webp" alt="Kabelota" width="140" height="30">
            <img class="for-dark" src="/images/brand/kabelota-putih.webp" alt="" width="140" height="30">
        </a>
        <p class="err-code mono">@yield('code')</p>
        <h1 class="h2">@yield('heading')</h1>
        <p class="lead">@yield('message')</p>
        <div class="err-actions">
            @section('actions')
                <a class="btn btn-accent" href="/">Ke beranda</a>
                <a class="btn btn-line" href="/talenta">Cari talenta</a>
            @show
        </div>
    </main>
</body>
</html>
