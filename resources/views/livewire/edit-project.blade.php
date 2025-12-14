<div class="w-full flex flex-col items-center gap-8 p-6" dir="rtl">

    {{-- Main Form Area --}}
    <div class="w-full lg:w-3/4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 p-8">

            {{-- Header --}}
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-100">تعديل المشروع</h2>
                    <p class="text-gray-400 text-sm mt-1">تعديل بيانات المشروع والمواصفات والصور</p>
                </div>
                <div class="w-12 h-12 bg-[#498e49]/20 rounded-full flex items-center justify-center text-[#498e49]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                </div>
            </div>

            {{-- Notifications (Toasts) --}}
            @if ($errors->any())
                <div class="fixed top-4 right-4 z-50 w-full max-w-sm" x-data="{ show: true }" x-show="show"
                    x-init="setTimeout(() => show = false, 5000)">
                    @foreach ($errors->all() as $error)
                        <div
                            class="bg-red-900/90 backdrop-blur-sm text-red-200 p-4 rounded-xl mb-2 border border-red-500/50 shadow-xl flex items-center justify-between">
                            <span>{{ $error }}</span>
                            <button @click="show = false" class="text-red-400 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Form Sections --}}
            <div class="flex flex-col gap-8">

                {{-- Basic Info --}}
                <div class="border-b border-neutral-700 pb-6">
                    <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                        البيانات الأساسية
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">اسم المشروع</label>
                            <input type="text" wire:model="name" placeholder="مثال: مشروع النخبة"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">الموقع</label>
                            <input type="text" wire:model="location" placeholder="مثال: حي الرياض"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">نوع المشروع</label>
                            <select wire:model="project_type"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                                <option value="">اختر النوع</option>
                                <option value="عمارة">عمارة</option>
                                <option value="فيلا">فيلا</option>
                                <option value="شقة">شقة</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">الحالة</label>
                            <select wire:model="status"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                                <option value="">اختر الحالة</option>
                                <option value="جديد">جديد</option>
                                <option value="تحت الانشاء">تحت الانشاء</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Details --}}
                <div class="border-b border-neutral-700 pb-6">
                    <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                        تفاصيل المشروع
                    </h3>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-400">الوصف</label>
                        <textarea wire:model="description" rows="4" placeholder="اكتب وصفاً مختصراً للمشروع..."
                            class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all resize-none"></textarea>
                    </div>
                </div>

                {{-- Image --}}
                <div class="pb-6">
                    <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                        صورة المشروع
                    </h3>
                    <div
                        class="border-2 border-dashed border-neutral-600 rounded-2xl p-8 hover:border-[#498e49] hover:bg-[#498e49]/5 transition-all text-center cursor-pointer relative bg-neutral-900/50">
                        <input type="file" wire:model="image" accept="image/*"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <div class="flex flex-col items-center gap-2 text-gray-400">
                            <div
                                class="w-16 h-16 bg-neutral-800 rounded-full flex items-center justify-center mb-2 overflow-hidden">
                                @if ($image)
                                    <img src="{{ $image->temporaryUrl() }}" class="w-full h-full object-cover">
                                @elseif($existingImage)
                                    <img src="{{ asset('storage/' . $existingImage) }}"
                                        class="w-full h-full object-cover">
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#498e49]/50"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                @endif
                            </div>
                            <span class="font-medium">اضغط هنا لتحديث الصورة</span>
                            <span class="text-xs text-gray-500">PNG, JPG up to 10MB</span>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="pt-4 border-t border-neutral-700 flex justify-end gap-3">
                    <a href="{{ route('projects-dashboard') }}"
                        class="bg-neutral-700 hover:bg-neutral-600 text-white px-8 py-3 rounded-xl font-bold transition-all">
                        إلغاء
                    </a>

                    <button wire:click="updateProject" wire:loading.attr="disabled"
                        class="bg-[#498e49] hover:bg-[#3d7a3d] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-[#498e49]/20 transition-all flex items-center gap-2">
                        <span wire:loading.remove>حفظ التعديلات</span>
                        <span wire:loading>جاري الحفظ...</span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
