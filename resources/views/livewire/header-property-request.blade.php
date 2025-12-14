<div class="w-full">
    {{-- Buttons --}}
    <div class="grid grid-cols-3 gap-2 w-full md:w-auto flex-1">
        <button wire:click="openForm('apartment')"
            class="group flex flex-col items-center justify-center gap-2 bg-white/5 hover:bg-[#49A035] border border-white/10 hover:border-[#49A035] rounded-xl py-4 px-2 transition-all duration-300 w-full cursor-pointer">
            <svg class="w-6 h-6 text-white group-hover:scale-110 transition-transform" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span class="text-white text-sm font-medium">شقة</span>
        </button>
        <button wire:click="openForm('villa')"
            class="group flex flex-col items-center justify-center gap-2 bg-white/5 hover:bg-[#49A035] border border-white/10 hover:border-[#49A035] rounded-xl py-4 px-2 transition-all duration-300 w-full cursor-pointer">
            <svg class="w-6 h-6 text-white group-hover:scale-110 transition-transform" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="text-white text-sm font-medium">فيلا</span>
        </button>
        <button wire:click="openForm('floor')"
            class="group flex flex-col items-center justify-center gap-2 bg-white/5 hover:bg-[#49A035] border border-white/10 hover:border-[#49A035] rounded-xl py-4 px-2 transition-all duration-300 w-full cursor-pointer">
            <img src="/img/floorsicon.svg" class="w-6 h-6 group-hover:scale-110 transition-transform" alt="دور">
            <span class="text-white text-sm font-medium">دور</span>
        </button>
    </div>

    {{-- Modal --}}
    @if ($isOpen)
        <div class="fixed inset-0  flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" x-data
            @keydown.escape.window="$wire.closeForm()">
            <div
                class="z-50 bg-white rounded-2xl w-full max-w-lg p-6 relative shadow-2xl animate-in fade-in zoom-in duration-300">
                <button wire:click="closeForm"
                    class="absolute top-4 left-4 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>

                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">سجل اهتمامك</h3>
                    <p class="text-gray-500">املأ البيانات وسنتواصل معك بخصوص عقار:
                        <span class="font-bold text-[#49A035]">
                            @if ($selectedType == 'villa')
                                فيلا
                            @elseif($selectedType == 'apartment')
                                شقة
                            @elseif($selectedType == 'floor')
                                دور
                            @endif
                        </span>
                    </p>
                </div>

                <form wire:submit.prevent="save" class="space-y-4 text-right">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الأول</label>
                            <input wire:model="first_name" type="text"
                                class="w-full px-4 py-3 rounded-xl border {{ $errors->has('first_name') ? 'border-red-500' : 'border-gray-200' }} focus:border-[#49A035] focus:ring focus:ring-[#49A035]/20 transition-colors">
                            @error('first_name')
                                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الأخير</label>
                            <input wire:model="last_name" type="text"
                                class="w-full px-4 py-3 rounded-xl border {{ $errors->has('last_name') ? 'border-red-500' : 'border-gray-200' }} focus:border-[#49A035] focus:ring focus:ring-[#49A035]/20 transition-colors">
                            @error('last_name')
                                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">رقم الجوال</label>
                        <input wire:model="phone" type="text" dir="ltr"
                            class="w-full px-4 py-3 rounded-xl border {{ $errors->has('phone') ? 'border-red-500' : 'border-gray-200' }} focus:border-[#49A035] focus:ring focus:ring-[#49A035]/20 transition-colors text-right"
                            placeholder="05xxxxxxxx">
                        @error('phone')
                            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" wire:loading.attr="disabled"
                            class="w-full bg-[#49A035] hover:bg-[#3d862d] text-white font-bold py-3.5 rounded-xl transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove>ارسال الطلب</span>
                            <span wire:loading>جاري الارسال...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Success Message Toast --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
            class="fixed bottom-4 right-4 bg-[#49A035] text-white px-6 py-3 rounded-xl shadow-lg z-50 flex items-center gap-2 animate-in slide-in-from-bottom duration-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            {{ session('message') }}
        </div>
    @endif
</div>
