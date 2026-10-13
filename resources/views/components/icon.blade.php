@props(['name'])
<svg
    {{ $attributes->merge(['class' => 'cc-icon', 'width' => 18, 'height' => 18]) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>
    @switch($name)
        @case('plus')
            <path d="M12 5v14m-7-7h14" />
        @break

        @case('chevron')
            <path d="m9 5 7 7-7 7" />
        @break

        @case('check')
            <path d="m5 12 4 4L19 6" />
        @break

        @case('alert')
            <path d="M12 9v4m0 4h.01M10.3 3.9 2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
        @break

        @case('clock')
            <path d="M12 8v4l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0" />
        @break

        @case('branch')
            <path d="M6 3v12a6 6 0 0 0 6 6m6-18v6a6 6 0 0 1-6 6H6" />
        @break

        @case('folder')
            <path d="M3 7h6l2 2h10v11H3ZM3 7V4h6l2 3" />
        @break

        @case('file')
            <path d="M14 2H5v20h14V7Zm0 0v5h5" />
        @break

        @case('copy')
            <path d="M9 9h12v12H9ZM15 9V3H3v12h6" />
        @break

        @case('theme')
            <path d="M21 12.8A9 9 0 0 1 11.2 3a9 9 0 1 0 9.8 9.8" />
        @break

        @case('search')
            <path d="m21 21-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0" />
        @break

        @case('repo')
            <path d="M4 3h16v18H4Zm0 14h16M8 7h8m-8 4h6" />
        @break

        @case('external')
            <path d="M14 3h7v7m0-7L10 14M10 3H3v18h18v-7" />
        @break

        @case('key')
            <path d="M15 7a5 5 0 1 1-10 0 5 5 0 0 1 10 0Zm-2 4 9 9m-4-4 2-2" />
        @break
    @endswitch
</svg>
