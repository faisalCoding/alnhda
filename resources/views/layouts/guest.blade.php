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

    {{-- Preload Hero Image for LCP Optimization. Whatever the front of the site
         is actually showing — an upload, a project cover, or the bundled
         photograph — since preloading a picture the page does not use costs a
         visitor bytes and still leaves the real one late. --}}
    @if (request()->routeIs('welcome'))
        <link rel="preload" href="{{ app(\App\Services\HomeFacts::class)->heroImage() }}" as="image"
            fetchpriority="high">
    @endif

    {{-- بقية الروابط وملفات الـ CSS --}}
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">

    {{-- The page heading is the largest text above the fold, so its own face is
         fetched at once rather than after the stylesheet has been parsed. --}}
    <link rel="preload" href="/fonts/changa/changa-200-arabic.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Styles -->
    <style>
        body {
            font-family: 'Almarai', sans-serif !important;
        }

        /* Changa ExtraLight, declared here rather than in the bundled
           stylesheet: Vite's dev server does not serve public/, so a
           /fonts/... url inside the bundle resolves to the dev server and
           404s while somebody is working. Declared in the page, it resolves
           against the site in every environment — and being in the head, it
           starts loading without waiting for the stylesheet.

           Served from this domain rather than a font CDN: the page
           heading is the largest text on the front of the site, and a headline that
           waits on a third party is a headline that arrives late. Licensed under the
           SIL Open Font License 1.1 — see public/fonts/changa/OFL.txt. */
        /* arabic */
        @font-face {
            font-family: 'Changa';
            font-style: normal;
            font-weight: 200;
            font-display: swap;
            src: url('/fonts/changa/changa-200-arabic.woff2') format('woff2');
            unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC, U+102E0-102FB, U+10E60-10E7E, U+10EC2-10EC4, U+10EFC-10EFF, U+1EE00-1EE03, U+1EE05-1EE1F, U+1EE21-1EE22, U+1EE24, U+1EE27, U+1EE29-1EE32, U+1EE34-1EE37, U+1EE39, U+1EE3B, U+1EE42, U+1EE47, U+1EE49, U+1EE4B, U+1EE4D-1EE4F, U+1EE51-1EE52, U+1EE54, U+1EE57, U+1EE59, U+1EE5B, U+1EE5D, U+1EE5F, U+1EE61-1EE62, U+1EE64, U+1EE67-1EE6A, U+1EE6C-1EE72, U+1EE74-1EE77, U+1EE79-1EE7C, U+1EE7E, U+1EE80-1EE89, U+1EE8B-1EE9B, U+1EEA1-1EEA3, U+1EEA5-1EEA9, U+1EEAB-1EEBB, U+1EEF0-1EEF1;
        }

        /* latin-ext */
        @font-face {
            font-family: 'Changa';
            font-style: normal;
            font-weight: 200;
            font-display: swap;
            src: url('/fonts/changa/changa-200-latin-ext.woff2') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }

        /* latin */
        @font-face {
            font-family: 'Changa';
            font-style: normal;
            font-weight: 200;
            font-display: swap;
            src: url('/fonts/changa/changa-200-latin.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }

        /* The layout sets the body font with !important, so a heading that wants a
           different face has to say so just as loudly. */
        .font-display {
            font-family: 'Changa', 'Almarai', sans-serif !important;
            font-weight: 200;
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
