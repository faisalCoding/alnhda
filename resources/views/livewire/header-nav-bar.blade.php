<nav x-data="{ open: false }" x-effect="document.body.classList.toggle('overflow-hidden', open)"
    x-on:keydown.escape.window="open = false" class="w-full mx-auto px-4 py-3 container">

    <div class="flex items-center justify-between gap-3">

        {{-- Logo (right side in RTL) --}}
        <a href="{{ route('welcome') }}" class="shrink-0" wire:navigate aria-label="الصفحة الرئيسية">
            <img src="/img/alnhdafooterlogo.webp" class="h-10 md:h-12 w-auto object-contain"
                alt="شعار كيان النهضة العقارية" width="800" height="346">
        </a>

        {{-- Desktop Menu --}}
        <div class="hidden md:flex flex-1 items-center justify-center gap-8">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                    class="text-gray-600 hover:text-[#498E49] font-medium transition-colors duration-300 relative group py-2"
                    wire:current{{ $link['exact'] ? '.exact' : '' }}="text-[#498E49] font-bold" wire:navigate.hover>
                    {{ $link['label'] }}
                    <span
                        class="absolute bottom-0 right-0 w-0 h-0.5 bg-[#498E49] transition-all duration-300 group-hover:w-full"></span>
                </a>
            @endforeach
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2 shrink-0">
            {{-- Visit Office (visible on every screen size) --}}
            <a href="{{ $officeMapUrl }}" target="_blank" rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 rounded-full bg-[#498E49] px-3 py-2 md:px-5 md:py-2.5 text-xs md:text-sm font-bold text-white shadow-lg shadow-[#498E49]/25 transition-all duration-300 hover:bg-[#3c763c] hover:shadow-xl active:scale-95">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.244-4.243a8 8 0 1111.315 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>زيارة المكتب</span>
            </a>

            {{-- Mobile Menu Button --}}
            <button x-on:click="open = true" type="button" x-bind:aria-expanded="open.toString()"
                aria-controls="mobile-menu" aria-label="فتح القائمة"
                class="md:hidden rounded-lg p-2 text-gray-600 transition-colors hover:bg-gray-100 hover:text-[#498E49] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#498E49]">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile Drawer (teleported out of the backdrop-blurred header, which would otherwise
         become the containing block for these fixed elements) --}}
    <template x-teleport="body">
        <div class="md:hidden">
        {{-- Backdrop --}}
        <div x-show="open" x-on:click="open = false" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 bg-zinc-900/50 backdrop-blur-sm"
            style="display: none;" aria-hidden="true"></div>

        {{-- Panel --}}
        <div id="mobile-menu" x-show="open" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed inset-y-0 right-0 z-50 flex w-72 max-w-[85%] flex-col bg-white shadow-2xl"
            style="display: none;">

            <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-4">
                <img src="/img/alnhdafooterlogo.webp" class="h-9 w-auto object-contain"
                    alt="شعار كيان النهضة العقارية" width="800" height="346">
                <button x-on:click="open = false" type="button" aria-label="إغلاق القائمة"
                    class="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-[#498E49]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}" wire:navigate x-on:click="open = false"
                        class="flex items-center justify-between rounded-xl px-4 py-3 font-medium text-gray-700 transition-colors hover:bg-[#f5fdf5] hover:text-[#498E49] active:bg-[#eaf6ea]"
                        wire:current{{ $link['exact'] ? '.exact' : '' }}="bg-[#f5fdf5] text-[#498E49] font-bold">
                        <span>{{ $link['label'] }}</span>
                        <svg class="h-4 w-4 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                @endforeach
            </div>

            <div class="border-t border-zinc-100 p-4">
                <a href="{{ $officeMapUrl }}" target="_blank" rel="noopener noreferrer"
                    x-on:click="open = false"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#498E49] px-4 py-3 font-bold text-white transition-colors hover:bg-[#3c763c]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.244-4.243a8 8 0 1111.315 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>زيارة المكتب</span>
                </a>
                <p class="mt-3 text-center text-xs text-zinc-400">جدة، المملكة العربية السعودية</p>
            </div>
        </div>
        </div>
    </template>
</nav>
