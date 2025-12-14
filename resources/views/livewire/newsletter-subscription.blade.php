<div class="w-full max-w-sm">
    @if (session()->has('message'))
        <div class="bg-[#498E49]/10 text-[#498E49] px-4 py-3 rounded-xl text-sm border border-[#498E49]/20 mb-4">
            {{ session('message') }}
        </div>
    @else
        <form wire:submit.prevent="subscribe" class="flex flex-col gap-3">
            <div class="relative">
                <input wire:model="email" type="email" placeholder="بريدك الإلكتروني"
                    class="w-full px-4 py-3 bg-white border {{ $errors->has('email') ? 'border-red-500' : 'border-zinc-200' }} rounded-xl focus:outline-hidden focus:border-[#498E49] focus:ring-1 focus:ring-[#498E49] transition-colors text-sm">
                @error('email')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled"
                class="w-full bg-[#498E49] hover:bg-[#386e38] text-white px-6 py-3 rounded-xl transition-all duration-300 transform font-medium text-sm shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center">
                <span wire:loading.remove>اشترك الآن</span>
                <span wire:loading>
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </span>
            </button>
        </form>
    @endif
</div>
