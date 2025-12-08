<!DOCTYPE html>
<html class="scroll-smooth" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />


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
</head>

<body
    class="transition_to_up dark:bg-[#0a0a0a] text-[#1b1b18] flex justify-stretch  min-h-screen flex-col w-screen duration-20 ">

    @section('header')
        <header class="flex justify-center items-center w-full shadow rounded-xl h-25 ">
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
