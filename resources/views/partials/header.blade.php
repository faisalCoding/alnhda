@php
    use App\Services\HomeFacts;

    $facts = app(HomeFacts::class);

    // The face of the site: the picture uploaded in the panel, or else the
    // cover of whichever project the panel put first.
    $heroImage = $facts->heroImage();
    $heroAlt = $facts->heroImageAlt();

    $guarantee = $facts->guarantees()[0] ?? null;
    $hero = $facts->hero();
@endphp

<section class="relative w-full min-h-[calc(100svh-5rem)] flex flex-col overflow-hidden bg-zinc-900" dir="rtl">

    {{-- Background --}}
    <div class="absolute inset-0 z-0">
        <img src="{{ $heroImage }}" alt="{{ $heroAlt }}" class="w-full h-full object-cover" fetchpriority="high"
            loading="eager" width="1600" height="900" onerror="this.src='{{ asset('img/homebg.webp') }}'">
        {{-- A photograph is brighter than flat concrete, so the text needs its
             own ground rather than the image being dimmed into grey mush. --}}
        <div class="absolute inset-0 bg-gradient-to-b from-black/75 via-black/55 to-black/80"></div>
    </div>

    {{-- Content --}}
    <div class="relative z-10 flex-1 flex items-center justify-center">
        <div class="container mx-auto px-4 py-16 md:py-20 flex flex-col items-center text-center">

        <p class="text-white/75 text-sm md:text-base mb-4 tracking-[0.2em] font-light">
            {{ $hero['eyebrow'] }}
        </p>

        <h1 class="font-display text-4xl md:text-6xl lg:text-7xl text-white mb-5 leading-[1.25] max-w-4xl drop-shadow-lg">
            {{ $hero['title'] }}
        </h1>

        <p class="text-gray-200 text-base md:text-lg mb-8 max-w-2xl leading-relaxed font-light">
            {{ $hero['subtitle'] }}
        </p>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <a href="{{ route('projects') }}"
                class="group inline-flex items-center gap-2 rounded-xl bg-[#498E49] px-8 py-3.5 text-base font-bold text-white shadow-lg shadow-black/30 transition-all duration-300 hover:bg-[#3c763c] hover:shadow-xl active:scale-95">
                {{ $hero['primary_label'] }}
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transition-transform group-hover:-translate-x-1"
                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>

            <a href="{{ route('contact-us') }}" x-data
                @click.prevent="$dispatch('open-contact-wizard')"
                class="inline-flex items-center gap-2 rounded-xl border-2 border-white/70 px-8 py-3 text-base font-bold text-white backdrop-blur-sm transition-all duration-300 hover:bg-white hover:text-[#498E49] active:scale-95">
                {{ $hero['secondary_label'] }}
            </a>
        </div>

        </div>
    </div>

    {{-- Credentials band: the licence and the guarantee are the first thing a
         serious buyer looks for, and they used to sit halfway down the page. --}}
    <div class="relative z-10 border-t border-white/15 bg-black/40 backdrop-blur-md">
        <div class="container mx-auto px-4 py-3">
            <ul class="flex flex-wrap items-center justify-center gap-x-8 gap-y-2 text-xs md:text-sm text-white/85">
                <li class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 text-[#7cc47c]" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    رخصة فال رقم {{ HomeFacts::FAL_LICENCE }}
                </li>

                <li class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 text-[#7cc47c]" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                    الرقم الموحد {{ HomeFacts::UNIFIED_NUMBER }}
                </li>

                @if ($guarantee)
                    <li class="hidden sm:inline-flex items-center gap-2">
                        <svg class="h-4 w-4 text-[#7cc47c]" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
                        </svg>
                        {{ $guarantee }}
                    </li>
                @endif
            </ul>
        </div>
    </div>

</section>
