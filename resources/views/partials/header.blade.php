<div class="relative w-full h-[85vh] min-h-[600px] flex items-center justify-center bg-zinc-900 overflow-hidden rtl"
    dir="rtl">
    {{-- Background Image --}}
    <div class="absolute inset-0 z-0">
        <img src="/img/frontvilla.jpg" alt="Background" class="w-full h-full object-cover opacity-60">
        <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/40 to-zinc-900/90"></div>
    </div>

    {{-- Content --}}
    <div class="relative z-10 container mx-auto px-4 flex flex-col items-center text-center">

        {{-- Logo --}}
        <div class="mb-10 transform hover:scale-105 transition-transform duration-500">
            <img src="/img/alnhdafooterlogo.png" alt="النهضة العقارية"
                class="w-48 md:w-56 drop-shadow-2xl [filter:brightness(10)]">
        </div>

        {{-- Headings --}}
        <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight max-w-4xl drop-shadow-lg">
            وجهتك الأولى نحو <span class="text-[#49A035]">المنزل المثالي</span>
        </h1>
        <p class="text-gray-200 text-lg md:text-xl mb-12 max-w-2xl leading-relaxed font-light">
            نقدم لك خيارات سكنية متنوعة تناسب احتياجاتك وتطلعاتك، بتصاميم عصرية وجودة بناء عالية.
        </p>

        {{-- Search/Filter Widget --}}
        <div
            class="w-full max-w-3xl bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-2 md:p-3 shadow-2xl">
            <div class="flex flex-col md:flex-row items-center justify-center gap-2 md:gap-4">
                <h2 class="text-white font-bold text-lg px-4 hidden md:block">عن ماذا تبحث؟</h2>

                <div class="grid grid-cols-3 gap-2 w-full md:w-auto flex-1">
                    <button
                        class="group flex flex-col items-center justify-center gap-2 bg-white/5 hover:bg-[#49A035] border border-white/10 hover:border-[#49A035] rounded-xl py-4 px-2 transition-all duration-300">
                        <svg class="w-6 h-6 text-white group-hover:scale-110 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="text-white text-sm font-medium">شقة</span>
                    </button>
                    <button
                        class="group flex flex-col items-center justify-center gap-2 bg-white/5 hover:bg-[#49A035] border border-white/10 hover:border-[#49A035] rounded-xl py-4 px-2 transition-all duration-300">
                        <svg class="w-6 h-6 text-white group-hover:scale-110 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span class="text-white text-sm font-medium">فيلا</span>
                    </button>
                    <button
                        class="group flex flex-col items-center justify-center gap-2 bg-white/5 hover:bg-[#49A035] border border-white/10 hover:border-[#49A035] rounded-xl py-4 px-2 transition-all duration-300">
                        <svg class="w-6 h-6 text-white group-hover:scale-110 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21h-9a2 2 0 01-2-2v-5.29a2 2 0 00-2-5.29 2.5 2.5 0 01.5-2.22l5-8.5a.64.64 0 01.56-.2V4a1.5 1.5 0 001.5 1.5A1.5 1.5 0 0115 7v10.59a2.5 2.5 0 01-.5 1.91l-5 8.5z" />
                        </svg>
                        <span class="text-white text-sm font-medium">دور</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Scroll Down --}}
    <a href="#projects"
        class="absolute bottom-10 left-1/2 transform -translate-x-1/2 z-20 flex flex-col items-center gap-2 group cursor-pointer">
        <span class="text-white/80 text-sm font-medium group-hover:text-white transition-colors">اكتشف مشاريعنا</span>
        <div
            class="w-8 h-12 border-2 border-white/30 rounded-full flex justify-center p-2 group-hover:border-white transition-colors">
            <div class="w-1 h-2 bg-white rounded-full animate-bounce"></div>
        </div>
    </a>
</div>
