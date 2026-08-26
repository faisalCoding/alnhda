<!DOCTYPE html>
<html class="scroll-smooth" lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />


    {{-- الأولوية: تخصيص محفوظ في اللوحة، ثم ما تقوله الصفحة عن نفسها، ثم الافتراضي العام. --}}
    @php
        $seo = app(\App\Services\SeoResolver::class)->forCurrentRoute([
            'title' => trim($__env->yieldContent('title')),
            'description' => trim($__env->yieldContent('description')),
            'image' => trim($__env->yieldContent('image')),
            'og_type' => trim($__env->yieldContent('og_type')),
        ]);
        $seoImage = $seo->image;
    @endphp

    <title>@if ($seo->title){{ $seo->title }} | @endif{{ config('app.name', 'كيان النهضة العقارية') }}</title>
    @include('partials.favicon')
    <link rel="canonical" href="{{ url()->current() }}">


    {{-- 2. الوصف --}}
    <meta name="description" content="{{ $seo->description ?: 'شركة متخصصة وذات خبرة في التطوير العقاري. نقدم أفضل الحلول السكنية والاستثمارية. اكتشف مشاريعنا الآن!' }}">

    @if ($seo->keywords)
        <meta name="keywords" content="{{ $seo->keywords }}">
    @endif

    @if ($seo->author)
        <meta name="author" content="{{ $seo->author }}">
    @endif

    @if ($seo->noindex)
        {{-- استُثنيت هذه الصفحة من الفهرسة من لوحة التحكم. --}}
        <meta name="robots" content="noindex, nofollow">
    @else
        {{-- max-image-preview:large يسمح لجوجل بعرض صور المشاريع كبيرة في
             النتائج بدل مصغّر، وهو ما يصنع الفارق لموقع يبيع بالصورة. --}}
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    @endif

    @if ($seo->themeColor)
        <meta name="theme-color" content="{{ $seo->themeColor }}">
    @endif

    {{-- 3. Open Graph (للواتساب وفيسبوك) --}}
    <meta property="og:site_name" content="كيان النهضة العقارية" />
    <meta property="og:title" content="{{ $seo->title ?: 'كيان النهضة العقارية' }}" />
    <meta property="og:description" content="{{ $seo->description ?: 'شركة متخصصة وذات خبرة في التطوير العقاري. نقدم أفضل الحلول السكنية والاستثمارية. اكتشف مشاريعنا الآن!' }}" />
    <meta property="og:image" content="{{ $seoImage }}" />
    <meta property="og:image:secure_url" content="{{ $seoImage }}" />
    @if ($seo->imageWidth())
        {{-- واتساب وفيسبوك يقرران «لافتة عريضة أم مربّع صغير» من هذين الرقمين قبل تحميل الصورة. --}}
        <meta property="og:image:width" content="{{ $seo->imageWidth() }}" />
        <meta property="og:image:height" content="{{ $seo->imageHeight() }}" />
    @endif
    <meta property="og:image:alt" content="{{ $seo->title ?: 'كيان النهضة العقارية' }}" />
    <meta property="og:type" content="{{ $seo->type }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:locale" content="ar_SA" />

    {{-- 4. Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $seo->title ?: 'كيان النهضة العقارية' }}" />
    <meta name="twitter:description" content="{{ $seo->description ?: 'شركة متخصصة وذات خبرة في التطوير العقاري. نقدم أفضل الحلول السكنية والاستثمارية. اكتشف مشاريعنا الآن!' }}" />
    <meta name="twitter:image" content="{{ $seoImage }}" />
    <meta name="twitter:image:alt" content="{{ $seo->title ?: 'كيان النهضة العقارية' }}" />

    {{-- Preload Hero Image for LCP Optimization --}}
    @if (request()->routeIs('home') || request()->path() == '/')
        <link rel="preload" href="/img/homebg.webp" as="image" type="image/webp" fetchpriority="high">
    @endif

    {{-- بقية الروابط وملفات الـ CSS --}}
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">

    <!-- Styles -->
    <style>
        body {
            font-family: 'Almarai', sans-serif !important;
        }
    </style>
    @vite('resources/css/app.css')

    @stack('styles')

    <!-- Google Tag Manager -->
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-5KBXGPRJ');
    </script>
    <!-- End Google Tag Manager -->

    {{-- Structured Data (JSON-LD) --}}
    @include('partials.structured-data')
    @stack('jsonld')
</head>

<body
    class="transition_to_up dark:bg-[#0a0a0a] text-[#1b1b18] flex justify-stretch  min-h-screen flex-col w-screen duration-20 ">

    <!-- Google Tag Manager (noscript)-->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5KBXGPRJ" height="0" width="0"
            style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    @section('header')
        <header x-data="{ scrolled: false }" x-on:scroll.window="scrolled = window.scrollY > 20"
            x-bind:class="scrolled ? 'h-16! md:h-20! bg-white/95! shadow-md!' : ''"
            class="sticky top-0 z-50 flex justify-center items-center w-full h-20 md:h-25 bg-white/80 backdrop-blur-md border-b border-white/10 shadow-sm transition-all duration-300">
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
