<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'لوحة التحكم') — كيان النهضة</title>
    @include('partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">

    <script>
        if (JSON.parse(localStorage.getItem('alnhda.panel.v1.ui.dark') ?? 'false')) {
            document.documentElement.classList.add('dark');
        }

        window.panelToggleDark = function () {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('alnhda.panel.v1.ui.dark', JSON.stringify(isDark));
        };
    </script>

    @vite(['resources/css/dashboard.css', 'resources/js/dashboard.js'])
</head>

<body class="min-h-screen bg-zinc-100 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100"
    x-data="{ sidebarOpen: false }">

    <div class="flex min-h-screen">
        @include('admin.partials.sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="sticky top-0 z-30 flex items-center justify-between gap-4 border-b border-zinc-200 bg-white/85 px-4 py-3 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/85 lg:px-8">
                <div class="flex items-center gap-3">
                    <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 lg:hidden"
                        @click="sidebarOpen = true" aria-label="فتح القائمة">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <h1 class="text-lg font-bold">@yield('heading', 'لوحة التحكم')</h1>
                </div>

                @include('admin.partials.sync-indicator')
            </header>

            <main class="flex-1 p-4 lg:p-8">
                @yield('content')
            </main>
        </div>
    </div>

    @include('admin.partials.relogin-modal')
    @include('admin.partials.upload-tray')
</body>

</html>
