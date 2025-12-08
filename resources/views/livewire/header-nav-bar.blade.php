<nav x-data="{ open: false }" class="container mx-auto px-4 py-3 bg-white/95 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-100 dark:bg-neutral-900/90 dark:border-neutral-800">
    <div class="flex justify-between items-center dir-rtl">
        
        {{-- Logo --}}
        <a href="{{ route('welcome') }}" class="flex-shrink-0" wire:navigate>
            <img src="/img/alnhdafooterlogo.png" class="h-12 w-auto object-contain" alt="Logo">
        </a>

        {{-- Desktop Menu --}}
        <div class="hidden md:flex items-center space-x-8 rtl:space-x-reverse">
            <a href="{{ route('welcome') }}" 
               class="text-gray-600 hover:text-[#498E49] font-medium transition-colors duration-300 relative group py-2"
               wire:current="text-[#498E49] font-bold"
               wire:navigate.hover>
                الرئيسية
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-[#498E49] transition-all duration-300 group-hover:w-full"></span>
            </a>
            
            <a href="{{ route('projects') }}" 
               class="text-gray-600 hover:text-[#498E49] font-medium transition-colors duration-300 relative group py-2"
               wire:current="text-[#498E49] font-bold"
               wire:navigate.hover>
                مشاريعنا
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-[#498E49] transition-all duration-300 group-hover:w-full"></span>
            </a>

            <a href="{{ route('blogs') }}" 
               class="text-gray-600 hover:text-[#498E49] font-medium transition-colors duration-300 relative group py-2"
               wire:current="text-[#498E49] font-bold"
               wire:navigate.hover>
                المقالات
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-[#498E49] transition-all duration-300 group-hover:w-full"></span>
            </a>

            <a href="#about" 
               class="text-gray-600 hover:text-[#498E49] font-medium transition-colors duration-300 relative group py-2">
                من نحن
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-[#498E49] transition-all duration-300 group-hover:w-full"></span>
            </a>

            <a href="#contact" 
               class="text-gray-600 hover:text-[#498E49] font-medium transition-colors duration-300 relative group py-2">
                تواصل معنا
                <span class="absolute bottom-0 right-0 w-0 h-0.5 bg-[#498E49] transition-all duration-300 group-hover:w-full"></span>
            </a>
        </div>

        {{-- Mobile Menu Button --}}
        <div class="md:hidden flex items-center">
            <button @click="open = !open" type="button" class="text-gray-600 hover:text-[#498E49] focus:outline-none p-2 rounded-md transition-colors">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" style="display: none;" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile Menu Dropdown --}}
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="md:hidden mt-4 pb-4 border-t border-gray-100 dark:border-neutral-800"
         style="display: none;">
        <div class="flex flex-col space-y-3 pt-4 px-2">
            <a href="{{ route('welcome') }}" wire:navigate class="text-gray-600 hover:text-[#498E49] font-medium block px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-neutral-800 transition-colors">
                الرئيسية
            </a>
            <a href="{{ route('projects') }}" wire:navigate class="text-gray-600 hover:text-[#498E49] font-medium block px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-neutral-800 transition-colors">
                مشاريعنا
            </a>
            <a href="{{ route('blogs') }}" wire:navigate class="text-gray-600 hover:text-[#498E49] font-medium block px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-neutral-800 transition-colors">
                المقالات
            </a>
            <a href="#about" class="text-gray-600 hover:text-[#498E49] font-medium block px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-neutral-800 transition-colors">
                من نحن
            </a>
            <a href="#contact" class="text-gray-600 hover:text-[#498E49] font-medium block px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-neutral-800 transition-colors">
                تواصل معنا
            </a>
        </div>
    </div>
</nav>