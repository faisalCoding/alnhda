<div class="relative w-full h-[85vh] min-h-[600px] flex items-center justify-center bg-zinc-900 rtl" dir="rtl">
    {{-- Background Image --}}
    <div class="absolute inset-0 z-0">
        <img src="/img/homebg.jpg" alt="Background" class="w-full h-full object-cover opacity-60">
    </div>

    {{-- Content --}}
    <div class="relative z-10 container mx-auto px-4 flex flex-col items-center text-center">

        {{-- Logo --}}
        <div class="mb-7 transform hover:scale-105 transition-transform duration-500">
            <img src="/img/alnhdafooterlogo.png" alt="النهضة العقارية"
                class="w-36 md:w-56 drop-shadow-2xl [filter:brightness(10)]">
        </div>

        {{-- Headings --}}
        <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight max-w-4xl drop-shadow-lg">
            أساسات راسخة.. لمستقبل آمن
        </h1>
        <p class="text-gray-200 text-lg md:text-xl mb-12 max-w-2xl leading-relaxed font-light">
            مشاريعنا نبنيها لتدوم و تبقى نموذجا للجودة و الاستدامة </p>

        {{-- Search/Filter Widget --}}
        <svg xmlns="http://www.w3.org/2000/svg" width="0" height="0"
            style="position: fixed; top: 0px; left: 0px; pointer-events: none; z-index: 9998;">
            <defs>
                <filter id="liquid-glass-mru1450kp_filter" filterUnits="userSpaceOnUse" colorInterpolationFilters="sRGB"
                    x="0" y="0" width="768" height="112">
                    <feImage id="liquid-glass-mru1450kp_map" width="768" height="112" xlink:href="/img/map.jpg">
                    </feImage>
                    <feDisplacementMap in="SourceGraphic" in2="liquid-glass-mru1450kp_map" xChannelSelector="R"
                        yChannelSelector="G" scale="48.700230958000276">
                    </feDisplacementMap>

                </filter>
            </defs>
        </svg>
        <div
            class="w-full max-w-3xl border hover:scale-110 duration-300 border-white/20 rounded-2xl p-2 md:p-3 shadow-2xl [backdrop-filter:url(#liquid-glass-mru1450kp_filter)_blur(2.5px)_contrast(1.01)_brightness(1.05)_saturate(1.1)]">
            <div class="flex flex-col md:flex-row items-center justify-center gap-2 md:gap-4">
                <h2 class="text-white font-bold text-lg px-4 hidden md:block">عن ماذا تبحث؟</h2>

                @livewire('header-property-request')
            </div>
        </div>
    </div>






    {{-- Scroll Down --}}
    <a href="#projects"
        class="hidden md:flex absolute z-0 bottom-10 left-1/2 transform -translate-x-1/2  flex-col items-center gap-2 group cursor-pointer">
        <span class="text-white/80 text-sm font-medium group-hover:text-white transition-colors">اكتشف مشاريعنا</span>
        <div
            class="w-8 h-12 border-2 border-white/30 rounded-full flex justify-center p-2 group-hover:border-white transition-colors">
            <div class="w-1 h-2 bg-white rounded-full animate-bounce"></div>
        </div>
    </a>

</div>
