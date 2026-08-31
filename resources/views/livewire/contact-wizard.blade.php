@php
    $roles = \App\Livewire\ContactWizard::ROLES;
    $unitTypes = \App\Livewire\ContactWizard::UNIT_TYPES;
@endphp

{{-- Steps live in Alpine: walking between three questions is not worth a
     network round trip each way, so only the finished answer reaches the
     server. Choices are pushed onto the Livewire component as they are made,
     which costs nothing until the form is actually submitted. --}}
<div x-data="{
    open: false,
    step: 1,
    role: '',
    unitType: '',
    done: false,
    asksUnitType() {
        return this.role === 'client';
    },
    get lastStep() {
        return this.asksUnitType() ? 3 : 2;
    },
    chooseRole(role) {
        this.role = role;
        $wire.role = role;
        this.unitType = '';
        $wire.unitType = '';
        this.step = 2;
    },
    chooseUnitType(type) {
        this.unitType = type;
        $wire.unitType = type;
        this.step = 3;
    },
    back() {
        if (this.step > 1) {
            this.step--;
        }
    },
    show() {
        this.done = false;
        this.step = 1;
        this.role = '';
        this.unitType = '';
        this.open = true;
    },
    close() {
        this.open = false;
    },
}"
    x-on:open-contact-wizard.window="show()"
    x-on:contact-wizard-saved="done = true"
    x-on:keydown.escape.window="close()">

    <div x-show="open" x-cloak class="fixed inset-0 z-[100] overflow-y-auto bg-white" dir="rtl"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" role="dialog" aria-modal="true" aria-labelledby="contact-wizard-title">

        {{-- Bar --}}
        <div class="sticky top-0 z-10 border-b border-gray-100 bg-white/95 backdrop-blur">
            <div class="container mx-auto flex items-center justify-between gap-4 px-4 py-4">
                <img src="/img/alnhda-logo.webp" alt="كيان النهضة العقارية" class="h-9 w-auto md:h-11" width="408"
                    height="182">

                <div class="flex items-center gap-3">
                    <button type="button" x-show="step > 1 && !done" x-cloak @click="back()"
                        class="rounded-xl px-4 py-2 text-sm font-bold text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-800">
                        رجوع
                    </button>
                    <button type="button" @click="close()"
                        class="rounded-xl p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                        aria-label="إغلاق">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Progress --}}
            <div class="h-1 w-full bg-gray-100" x-show="!done">
                {{-- Progress through the steps taken, not the steps shown: before a
                     role is chosen the flow does not yet know how long it is. --}}
                <div class="h-full bg-[#498E49] transition-all duration-300"
                    :style="`width: ${((step - 1) / (lastStep - 1)) * 100}%`"></div>
            </div>
        </div>

        <div class="container mx-auto flex min-h-[calc(100vh-6rem)] max-w-3xl flex-col justify-center px-4 py-12">

            {{-- Step 1: who --}}
            <div x-show="step === 1 && !done">
                <h2 id="contact-wizard-title" class="font-display mb-3 text-3xl md:text-5xl leading-[1.3] text-gray-900">
                    التواصل معنا بصفتك
                </h2>
                <p class="mb-10 text-lg font-light text-gray-500">اختر ما ينطبق عليك لنوجّه طلبك للفريق المناسب.</p>

                <div class="flex flex-col gap-4">
                    @foreach ($roles as $key => $role)
                        <button type="button" @click="chooseRole('{{ $key }}')"
                            class="group flex items-center justify-between gap-4 rounded-2xl border-2 border-gray-200 p-6 text-right transition-all duration-200 hover:border-[#498E49] hover:bg-[#498E49]/5">
                            <span>
                                <span class="block text-xl font-bold text-gray-900 group-hover:text-[#498E49]">
                                    {{ $role['label'] }}
                                </span>
                                <span class="mt-1 block text-sm font-light text-gray-500">{{ $role['hint'] }}</span>
                            </span>
                            <svg class="h-6 w-6 shrink-0 text-gray-300 transition-all group-hover:-translate-x-1 group-hover:text-[#498E49]"
                                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Step 2 for a buyer: what --}}
            <div x-show="step === 2 && asksUnitType() && !done" x-cloak>
                <h2 class="font-display mb-3 text-3xl md:text-5xl leading-[1.3] text-gray-900">ما الذي تبحث عنه؟</h2>
                <p class="mb-10 text-lg font-light text-gray-500">اختر نوع الوحدة التي تناسبك.</p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @foreach ($unitTypes as $key => $label)
                        <button type="button" @click="chooseUnitType('{{ $key }}')"
                            class="group flex flex-col items-center gap-4 rounded-2xl border-2 border-gray-200 p-8 transition-all duration-200 hover:border-[#498E49] hover:bg-[#498E49]/5">
                            <span class="rounded-full bg-[#498E49]/10 p-4 text-[#498E49]">
                                @if ($key === 'villa')
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                    </svg>
                                @elseif ($key === 'floor')
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                    </svg>
                                @else
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                @endif
                            </span>
                            <span class="text-xl font-bold text-gray-900 group-hover:text-[#498E49]">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Last step: how to reach you --}}
            <div x-show="!done && step > 1 && step === lastStep" x-cloak>
                <h2 class="font-display mb-3 text-3xl md:text-5xl leading-[1.3] text-gray-900">كيف نتواصل معك؟</h2>
                <p class="mb-10 text-lg font-light text-gray-500">اترك بياناتك ويصلك اتصال من فريقنا في أقرب وقت.</p>

                <form wire:submit="save" class="flex flex-col gap-5">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label for="wizard-first-name" class="mb-2 block font-bold text-gray-700">الاسم الأول</label>
                            <input id="wizard-first-name" type="text" wire:model="first_name"
                                class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 outline-none transition-colors focus:border-[#498E49]">
                            @error('first_name')
                                <p class="mt-1.5 text-sm font-medium text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="wizard-last-name" class="mb-2 block font-bold text-gray-700">اسم العائلة</label>
                            <input id="wizard-last-name" type="text" wire:model="last_name"
                                class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 outline-none transition-colors focus:border-[#498E49]">
                            @error('last_name')
                                <p class="mt-1.5 text-sm font-medium text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="wizard-phone" class="mb-2 block font-bold text-gray-700">رقم الجوال</label>
                        <input id="wizard-phone" type="tel" inputmode="tel" wire:model="phone" dir="ltr"
                            placeholder="05XXXXXXXX"
                            class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-right outline-none transition-colors focus:border-[#498E49]">
                        @error('phone')
                            <p class="mt-1.5 text-sm font-medium text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="wizard-email" class="mb-2 block font-bold text-gray-700">
                            البريد الإلكتروني <span class="font-normal text-gray-400">(اختياري)</span>
                        </label>
                        <input id="wizard-email" type="email" wire:model="email" dir="ltr"
                            class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 text-right outline-none transition-colors focus:border-[#498E49]">
                        @error('email')
                            <p class="mt-1.5 text-sm font-medium text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('role')
                        <p class="text-sm font-medium text-red-500">{{ $message }}</p>
                    @enderror
                    @error('unitType')
                        <p class="text-sm font-medium text-red-500">{{ $message }}</p>
                    @enderror

                    <button type="submit" wire:loading.attr="disabled"
                        class="mt-2 rounded-xl bg-[#498E49] px-8 py-4 text-lg font-bold text-white shadow-lg transition-all duration-300 hover:bg-[#3c763c] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">إرسال الطلب</span>
                        <span wire:loading wire:target="save">جارٍ الإرسال…</span>
                    </button>
                </form>
            </div>

            {{-- Done --}}
            <div x-show="done" x-cloak class="text-center">
                <span class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-[#498E49]/10 text-[#498E49]">
                    <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </span>

                <h2 class="font-display mb-3 text-3xl md:text-5xl leading-[1.3] text-gray-900">وصلنا طلبك</h2>
                <p class="mb-10 text-lg font-light text-gray-500">
                    شكرًا لتواصلك مع كيان النهضة العقارية. سيصلك اتصال من فريقنا في أقرب وقت.
                </p>

                <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <button type="button" @click="close()"
                        class="rounded-xl bg-[#498E49] px-8 py-3.5 font-bold text-white transition-colors hover:bg-[#3c763c]">
                        العودة للموقع
                    </button>
                    <a href="{{ route('projects') }}"
                        class="rounded-xl border-2 border-[#498E49] px-8 py-3 font-bold text-[#498E49] transition-colors hover:bg-[#498E49] hover:text-white">
                        تصفّح المشاريع
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
