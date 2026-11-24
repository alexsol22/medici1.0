<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ config('brand.colors.page') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ config('brand.short_name') }}">
<link rel="apple-touch-icon" href="{{ asset(config('brand.icons.apple')) }}">
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(@json(asset('sw.js')));
    }
</script>
