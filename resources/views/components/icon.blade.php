@props(['name' => 'grid', 'size' => 20])
@php
    $icons = [
        'grid' => ['M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
        'cart' => ['M3 3h2l3 12h11l3-9H6', 'M9 20h.01M19 20h.01'],
        'box' => ['M21 8l-9 5-9-5M12 13v9M3 7l9-5 9 5v10l-9 5-9-5zM7.5 4.5l9 5'],
        'layers' => ['M12 3l10 6-10 6L2 9zM2 13l10 6 10-6M2 17l10 6 10-6'],
        'receipt' => ['M5 3h14v19l-3-2-4 2-4-2-3 2zM9 7h6M9 11h6M9 15h3'],
        'file' => ['M14 2H5v20h14V7zM14 2v5h5M8 12h8M8 16h6'],
        'users' => ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 4a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87', 'M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0'],
        'wallet' => ['M20 8V3H5a3 3 0 0 0-3 3v13a2 2 0 0 0 2 2h17V8H5a3 3 0 0 1 0-6M21 12h-6v5h6M17 14.5h.01'],
        'expense' => ['M12 3v12M7 10l5 5 5-5M4 16v5h16v-5'],
        'settings' => ['M4 7h16M4 17h16M8 4v6M16 14v6'],
        'shield' => ['M12 2l9 4v6c0 5-9 10-9 10S3 17 3 12V6zM8 12l3 3 5-6'],
        'search' => ['M21 21l-5-5', 'M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0'],
        'bell' => ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4'],
        'chevron' => ['M9 5l7 7-7 7'], 'down' => ['M6 9l6 6 6-6'],
        'plus' => ['M12 5v14M5 12h14'], 'arrow' => ['M5 12h14M13 6l6 6-6 6'],
        'trend' => ['M3 17l6-6 4 4L22 6M15 6h7v7'],
        'clock' => ['M12 8v4l3 2', 'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0'],
        'calendar' => ['M8 2v4M16 2v4M3 10h18M3 4h18v18H3z'],
        'check' => ['M5 12l4 4L19 6'], 'alert' => ['M12 3L2 21h20zM12 9v5M12 17h.01'],
        'menu' => ['M3 6h18M3 12h18M3 18h18'], 'close' => ['M6 6l12 12M6 18L18 6'],
        'logout' => ['M9 3H3v18h6M12 12h10M18 8l4 4-4 4'],
        'user' => ['M20 21v-2a7 7 0 0 0-14 0v2', 'M17 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0'],
        'scan' => ['M8 3H3v5M16 3h5v5M3 16v5h5M21 16v5h-5M7 7v10M11 7v10M14 7v10M17 7v10'],
        'trash' => ['M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7'],
        'printer' => ['M6 9V2h12v7M6 18H3v-9h18v9h-3M6 14h12v8H6zM17 12h.01'],
        'spark' => ['M12 3l2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z'],
    ];
@endphp
<svg {{ $attributes->class(['app-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @foreach($icons[$name] ?? $icons['grid'] as $path)
        <path d="{{ $path }}" />
    @endforeach
</svg>