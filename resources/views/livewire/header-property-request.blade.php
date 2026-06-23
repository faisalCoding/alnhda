@php
    $types = [
        'apartment' => [
            'label' => 'شقة',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
        ],
        'villa' => [
            'label' => 'فيلا',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
        ],
        'floor' => [
            'label' => 'دور',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>',
        ],
    ];
@endphp

<div class="w-full">

    {{-- Buttons --}}
    <div class="grid grid-cols-3 gap-2 w-full md:w-auto flex-1">
        @foreach ($types as $key => $type)
            <button wire:click="openForm('{{ $key }}')"
                class="group flex flex-col items-center justify-center gap-2 bg-white/5 hover:bg-[#49A035] border border-white/10 hover:border-[#49A035] rounded-xl py-4 px-2 transition-all duration-300 w-full cursor-pointer">
                @if ($key === 'floor')
                    <img src="/img/floorsicon.svg" class="w-6 h-6 group-hover:scale-110 transition-transform" alt="دور">
                @else
                    <svg class="w-6 h-6 text-white group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $type['icon'] !!}
                    </svg>
                @endif
                <span class="text-white text-sm font-medium">{{ $type['label'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Modal --}}
    @if ($isOpen)
        @teleport('body')
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
             x-data
             @keydown.escape.window="$wire.closeForm()"
             @click.self="$wire.closeForm()">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

            {{-- Card --}}
            <div class="relative z-10 w-full max-w-md rounded-3xl overflow-hidden shadow-2xl" dir="rtl">

                {{-- Header --}}
                <div class="relative bg-gradient-to-bl from-[#1e5c1e] to-[#2d7a2d] px-8 pt-8 pb-10 text-white text-right overflow-hidden">

                    {{-- Decorative circles --}}
                    <div class="absolute -top-6 -left-6 w-32 h-32 rounded-full bg-white/5"></div>
                    <div class="absolute top-4 -left-2 w-16 h-16 rounded-full bg-white/5"></div>
                    <div class="absolute -bottom-8 -right-8 w-40 h-40 rounded-full bg-black/10"></div>

                    {{-- Close button --}}
                    <button wire:click="closeForm"
                        class="absolute top-4 left-4 text-white/60 hover:text-white transition-colors p-1.5 hover:bg-white/10 rounded-full">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    {{-- Property icon + type --}}
                    <div class="flex items-center gap-4 mb-4 relative">
                        <div class="w-14 h-14 rounded-2xl bg-white/15 flex items-center justify-center flex-shrink-0">
                            @php $current = $types[$selectedType] ?? $types['apartment']; @endphp
                            @if ($selectedType === 'floor')
                                <img src="/img/floorsicon.svg" class="w-7 h-7 brightness-[100]" alt="دور">
                            @else
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    {!! $current['icon'] !!}
                                </svg>
                            @endif
                        </div>
                        <div>
                            <p class="text-white/70 text-sm">نوع العقار</p>
                            <h3 class="text-2xl font-bold">{{ $current['label'] }}</h3>
                        </div>
                    </div>

                    <p class="text-white/80 text-sm leading-relaxed relative">
                        سجّل اهتمامك وسيتواصل معك فريقنا في أقرب وقت
                    </p>

                    {{-- Logo --}}
                    <div class="absolute bottom-4 left-6 opacity-20">
                        <img src="/img/alnhdafooterlogo.webp" alt="كيان النهضة" class="h-10 brightness-[100]">
                    </div>
                </div>

                {{-- Curved connector --}}
                <div class="h-5 bg-white relative -mt-5 rounded-t-[2rem]"></div>

                {{-- Form --}}
                <div class="bg-white px-8 pb-8 text-right">
                    <form wire:submit.prevent="save" class="space-y-4">

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1.5">الاسم الأول</label>
                                <input wire:model="first_name" type="text"
                                    class="w-full px-4 py-3 rounded-xl border text-sm bg-gray-50 focus:bg-white {{ $errors->has('first_name') ? 'border-red-400 bg-red-50' : 'border-gray-200' }} focus:border-[#49A035] focus:ring-2 focus:ring-[#49A035]/20 outline-none transition-all"
                                    placeholder="محمد">
                                @error('first_name')
                                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1.5">الاسم الأخير</label>
                                <input wire:model="last_name" type="text"
                                    class="w-full px-4 py-3 rounded-xl border text-sm bg-gray-50 focus:bg-white {{ $errors->has('last_name') ? 'border-red-400 bg-red-50' : 'border-gray-200' }} focus:border-[#49A035] focus:ring-2 focus:ring-[#49A035]/20 outline-none transition-all"
                                    placeholder="الأحمدي">
                                @error('last_name')
                                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1.5">رقم الجوال</label>
                            <input wire:model="phone" type="tel" dir="ltr"
                                class="w-full px-4 py-3 rounded-xl border text-sm bg-gray-50 focus:bg-white {{ $errors->has('phone') ? 'border-red-400 bg-red-50' : 'border-gray-200' }} focus:border-[#49A035] focus:ring-2 focus:ring-[#49A035]/20 outline-none transition-all text-right"
                                placeholder="05xxxxxxxx">
                            @error('phone')
                                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <button type="submit" wire:loading.attr="disabled"
                            class="w-full bg-[#49A035] hover:bg-[#3d862d] active:scale-[0.98] text-white font-bold py-3.5 rounded-2xl transition-all shadow-lg shadow-[#49A035]/30 hover:shadow-[#49A035]/50 disabled:opacity-60 disabled:cursor-not-allowed mt-2">
                            <span wire:loading.remove class="flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                إرسال الطلب
                            </span>
                            <span wire:loading class="flex items-center justify-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                جاري الإرسال...
                            </span>
                        </button>

                        {{-- Trust indicator --}}
                        <p class="text-center text-xs text-gray-400 flex items-center justify-center gap-1.5 pt-1">
                            <svg class="w-3.5 h-3.5 text-[#49A035]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            بياناتك محمية وسرية تماماً
                        </p>
                    </form>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Success Toast --}}
    @if (session()->has('message'))
        @teleport('body')
        <div x-data="{ show: true }" x-show="show"
             x-init="setTimeout(() => show = false, 3500)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[9999] bg-[#49A035] text-white px-6 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 whitespace-nowrap">
            <div class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <span class="font-semibold text-sm">{{ session('message') }}</span>
        </div>
        @endteleport
    @endif
</div>
