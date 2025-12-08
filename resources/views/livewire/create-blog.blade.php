<div class="w-full flex flex-col lg:flex-row gap-8 p-6" dir="rtl">
    
    {{-- Sidebar/Instructions --}}
    <div class="w-full lg:w-1/4 flex flex-col gap-4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 overflow-hidden p-6">
            <h3 class="font-bold text-[#498e49] text-center mb-4">تعليمات بناء المقال</h3>
            <p class="text-gray-400 text-sm mb-2">1. ابدأ بإضافة العناصر من الجذر.</p>
            <p class="text-gray-400 text-sm mb-2">2. يمكنك إضافة عناصر فرعية داخل أي عنصر.</p>
            <p class="text-gray-400 text-sm mb-2">3. استخدم "Has Content" لإضافة نصوص.</p>
            <p class="text-gray-400 text-sm">4. املأ الاسم والوسوم (Classes) لتنسيق العنصر.</p>
        </div>
    </div>

    {{-- Main Builder Area --}}
    <div class="w-full lg:w-3/4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 p-8">
            
            <div class="mb-8 flex items-center justify-between">
                <div>
                     <h2 class="text-2xl font-bold text-gray-100">منشئ المقالات</h2>
                     <p class="text-gray-400 text-sm mt-1">قم ببناء هيكل المقال باستخدام العلامات المتداخلة</p>
                </div>
            </div>

            @if (session()->has('message'))
                <div class="fixed top-4 right-4 z-50 w-full max-w-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                    <div class="bg-[#498e49]/90 backdrop-blur-sm text-white p-4 rounded-xl mb-6 border border-[#498e49]/50 shadow-xl flex items-center justify-between">
                        <span>{{ session('message') }}</span>
                         <button @click="show = false" class="text-white/70 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                    </div>
                </div>
            @endif

            {{-- Basic Blog Info --}}
            <div class="mb-8 border-b border-neutral-700 pb-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-400">عنوان المقال</label>
                        <input type="text" wire:model="title" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-400">صورة الغلاف</label>
                        <div class="relative">
                            <input type="file" wire:model="image" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tag Tree Builder --}}
            <div class="mb-8">
                <h3 class="font-bold text-gray-300 mb-4 border-b border-neutral-700 w-fit pb-1">هيكل المقال</h3>
                
                <x-blog-tag-tree :tags="$tags" />
                
                <button wire:click="addTag" class="mt-4 w-full py-3 border-2 border-dashed border-neutral-600 text-neutral-400 rounded-xl hover:border-[#498e49] hover:text-[#498e49] hover:bg-[#498e49]/5 transition-all flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    إضافة عنصر جذري جديد
                </button>
            </div>

            <div class="pt-4 border-t border-neutral-700 flex justify-end">
                 <button wire:click="save" class="bg-[#498e49] hover:bg-[#3d7a3d] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-[#498e49]/20 transition-all">
                     حفظ المقال
                 </button>
            </div>

        </div>
    </div>
</div>
