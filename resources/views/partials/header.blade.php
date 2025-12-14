<div class="relative w-full h-[85vh] min-h-[600px] flex items-center justify-center bg-zinc-900 overflow-hidden rtl"
    dir="rtl">
    {{-- Background Image --}}
    <div class="absolute inset-0 z-0">
        <img src="/img/bg-home-page.jpeg" alt="Background" class="w-full h-full object-cover opacity-60">
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

                @livewire('header-property-request')
            </div>
        </div>
    </div>

    {{-- Scroll Down --}}
    <a href="#projects"
        class="absolute z-0 bottom-10 left-1/2 transform -translate-x-1/2 flex flex-col items-center gap-2 group cursor-pointer">
        <span class="text-white/80 text-sm font-medium group-hover:text-white transition-colors">اكتشف مشاريعنا</span>
        <div
            class="w-8 h-12 border-2 border-white/30 rounded-full flex justify-center p-2 group-hover:border-white transition-colors">
            <div class="w-1 h-2 bg-white rounded-full animate-bounce"></div>
        </div>
    </a>
</div>
