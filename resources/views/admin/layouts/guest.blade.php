<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'تسجيل الدخول') — كيان النهضة</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">

    <script>
        if (JSON.parse(localStorage.getItem('alnhda.panel.v1.ui.dark') ?? 'false')) {
            document.documentElement.classList.add('dark');
        }
    </script>

    @vite('resources/css/dashboard.css')
</head>

<body class="flex min-h-screen items-center justify-center bg-zinc-100 p-4 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="w-full max-w-md">
        <div class="mb-8 flex flex-col items-center gap-3">
            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-500 text-2xl font-extrabold text-white">ن</span>
            <div class="text-center">
                <p class="text-lg font-extrabold">كيان النهضة العقارية</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">لوحة التحكم</p>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
            @yield('content')
        </div>
    </div>
</body>

</html>
