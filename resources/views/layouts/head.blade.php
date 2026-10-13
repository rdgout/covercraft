<meta charset="utf-8">
<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>
<meta
    name="csrf-token"
    content="{{ csrf_token() }}"
>
<title>CoverCraft</title>
<script>
    (() => {
        let theme;
        try {
            theme = localStorage.getItem('covercraft-theme');
        } catch {}
        document.documentElement.dataset.theme = theme === 'dark' || theme === 'light' ?
            theme : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
