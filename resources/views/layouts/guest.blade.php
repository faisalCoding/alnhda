<!DOCTYPE html>
<html class="scroll-smooth" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />


    <title>@yield('title') | {{ config('app.name', 'كيان النهضة العقارية') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/KNicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('img/KNicon.png') }}">

    {{-- 2. الوصف --}}
    {{-- الوصف الافتراضي الذي قدمته --}}
    <meta name="description" content="@yield('description', 'شركة متخصصة وذات خبرة في التطوير العقاري، نقدم أفضل الحلول السكنية والاستثمارية.')">

    {{-- 3. Open Graph (للواتساب وفيسبوك) --}}
    <meta property="og:site_name" content="كيان النهضة العقارية" />
    <meta property="og:title" content="@yield('title', 'كيان النهضة العقارية')" />
    <meta property="og:description" content="@yield('description', 'شركة متخصصة وذات خبرة في التطوير العقاري.')" />
    <meta property="og:image" content="@yield('image', asset('images/logo.png'))" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />

    {{-- بقية الروابط وملفات الـ CSS --}}

    <title>@yield('title', env('APP_NAME'))</title>

    <!-- Fonts -->
    <link href="https://fonts.cdnfonts.com/css/neo-sans-arabic" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/almarai" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Styles -->
    <style>
        body {
            font-family: 'Almarai', sans-serif !important;
        }
    </style>
    @vite('resources/css/app.css')

    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-Q03JH1MFXB"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() {
            dataLayer.push(argu ments);
        }
        gtag('js', new Date());

        gtag('config', 'G-Q03JH1MFXB');
    </script>
</head>

<body
    class="transition_to_up dark:bg-[#0a0a0a] text-[#1b1b18] flex justify-stretch  min-h-screen flex-col w-screen duration-20 ">

    @section('header')
        <header class="sticky top-0 z-50 flex justify-center items-center w-full h-25 ">
            @livewire('header-nav-bar')
        </header>
    @show
    <div class=" flex items-stretch flex-col w-screen grow">
        @section('main')

        @show
    </div>

    @include('partials.whatsapp')
    @include('partials.footer')
    @vite('resources/js/app.js')

</body>

</html>
