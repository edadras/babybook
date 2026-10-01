<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'استودیو داستان') · استودیو داستان هوشمند</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('vendor/story-studio/studio.css') }}?v={{ filemtime(public_path('vendor/story-studio/studio.css')) }}">
    <script src="{{ asset('vendor/story-studio/studio.js') }}?v={{ filemtime(public_path('vendor/story-studio/studio.js')) }}" defer></script>
</head>
<body class="@yield('body-class')">
    <header class="topbar">
        <a href="{{ route('story-studio.index') }}" class="brand">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>استودیو داستان هوشمند<small>AI Story Studio</small></span>
        </a>
        @hasSection('crumb')
            <span class="crumb">/ <strong>@yield('crumb')</strong></span>
        @endif
        <span class="spacer"></span>
        @yield('topbar-actions')
    </header>

    @yield('body')
</body>
</html>
