<div class="w-full flex flex-col lg:flex-row gap-8 p-6" dir="rtl">

    {{-- Sidebar: Existing Articles --}}
    <div class="w-full lg:w-1/4 flex flex-col gap-4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 overflow-hidden">
            <div class="bg-[#498e49]/20 p-4 border-b border-[#498e49]/20">
                <h3 class="font-bold text-[#498e49] text-center">المقالات المنشأة</h3>
            </div>
            <div class="p-2 flex flex-col gap-2 max-h-[600px] overflow-y-auto">
                @foreach (\App\Models\Blog::latest()->get() as $blog)
                    <div class="flex justify-between items-center bg-neutral-700/50 p-3 rounded-xl text-gray-200 hover:bg-[#498e49] hover:text-white transition-all duration-200 group">
                        <span class="font-medium cursor-pointer flex-grow truncate px-2">{{ $blog->title }}</span>
                        <button wire:click="delete({{ $blog->id }})" wire:confirm="هل أنت متأكد من حذف هذا المقال؟" class="text-gray-400 hover:text-red-200 group-hover:text-white transition-colors p-1">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                             </svg>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Main Form Area --}}
    <div class="w-full lg:w-3/4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 p-8">
            
            {{-- Header --}}
            <div class="mb-8 flex items-center justify-between">
                <div>
                     <h2 class="text-2xl font-bold text-gray-100">إضافة مقال جديد</h2>
                     <p class="text-gray-400 text-sm mt-1">قم بتعبئة البيانات التالية لنشر مقال جديد</p>
                </div>
               <div class="w-12 h-12 bg-[#498e49]/20 rounded-full flex items-center justify-center text-[#498e49]">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                    </svg>
               </div>
            </div>

            {{-- Notifications (Toasts) --}}
            @if ($errors->any())
                <div class="fixed top-4 right-4 z-50 w-full max-w-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                    @foreach ($errors->all() as $error)
                        <div class="bg-red-900/90 backdrop-blur-sm text-red-200 p-4 rounded-xl mb-2 border border-red-500/50 shadow-xl flex items-center justify-between">
                            <span>{{ $error }}</span>
                            <button @click="show = false" class="text-red-400 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

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

            {{-- Form Sections --}}
            <div class="flex flex-col gap-8">
                
                {{-- Details --}}
                <div class="border-b border-neutral-700 pb-6">
                    <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                        تفاصيل المقال
                    </h3>
                    <div class="flex flex-col gap-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">عنوان المقال</label>
                            <input type="text" wire:model="title" placeholder="اكتب عنواناً جذاباً للمقال" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>

                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">المحتوى</label>
                            <textarea wire:model="content" rows="10" placeholder="اكتب محتوى المقال هنا..." class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all resize-none"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Image --}}
                <div class="pb-6">
                     <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                         صورة المقال
                    </h3>
                    <div class="border-2 border-dashed border-neutral-600 rounded-2xl p-8 hover:border-[#498e49] hover:bg-[#498e49]/5 transition-all text-center cursor-pointer relative bg-neutral-900/50">
                         <input type="file" wire:model="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                         <div class="flex flex-col items-center gap-2 text-gray-400">
                             <div class="w-16 h-16 bg-neutral-800 rounded-full flex items-center justify-center mb-2">
                                 @if ($image)
                                     <img src="{{ $image->temporaryUrl() }}" class="w-full h-full rounded-full object-cover p-1">
                                 @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#498e49]/50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                     </svg>
                                 @endif
                             </div>
                             <span class="font-medium">اضغط هنا لرفع صورة المقال</span>
                             <span class="text-xs text-gray-500">PNG, JPG up to 10MB</span>
                         </div>
                         @if ($image)
                            <div class="mt-4">
                                <button wire:click.prevent="removePhoto" class="text-red-400 hover:underline text-sm z-30 relative">إزالة الصورة</button>
                            </div>
                         @endif
                    </div>
                </div>

                {{-- Submit --}}
                <div class="pt-4 border-t border-neutral-700 flex justify-end">
                    <button wire:click="create" wire:loading.attr="disabled" class="bg-[#498e49] hover:bg-[#3d7a3d] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-[#498e49]/20 transition-all flex items-center gap-2">
                         <span wire:loading.remove>نشر المقال</span>
                         <span wire:loading>جاري النشر...</span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
