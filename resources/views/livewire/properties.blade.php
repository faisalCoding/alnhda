<div class="w-full flex flex-col lg:flex-row gap-8 p-6" dir="rtl">

    {{-- Sidebar: Existing Properties --}}
    <div class="w-full lg:w-1/4 flex flex-col gap-4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 overflow-hidden">
            <div class="bg-[#498e49]/20 p-4 border-b border-[#498e49]/20">
                <h3 class="font-bold text-[#498e49] text-center">الوحدات المنشأة</h3>
            </div>
            <div class="p-2 flex flex-col gap-2 max-h-[600px] overflow-y-auto">
                @foreach (\App\Models\Project::with('properties')->latest()->get() as $project)
                    <div x-data="{ open: false }" class="bg-neutral-700/50 p-3 rounded-xl text-gray-200">
                        <div @click="open = !open" class="flex justify-between items-center cursor-pointer">
                            <h4 class="font-bold text-[#498e49]">{{ $project->name }}</h4>
                            <svg :class="{ 'rotate-90': open }" class="w-4 h-4 text-[#498e49] transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                        <div x-show="open" x-collapse class="mt-2">
                            @if ($project->properties->isNotEmpty())
                                <div class="flex flex-col gap-1">
                                    @foreach ($project->properties as $property)
                                        <div class="flex justify-between items-center bg-neutral-600/50 p-2 rounded-lg text-gray-200 hover:bg-[#498e49] hover:text-white transition-all duration-200 group">
                                            <span class="font-medium cursor-pointer flex-grow">{{ $property->name }}</span>
                                            <button wire:click="deleteProperty({{ $property->id }})" wire:confirm="Are you sure you want to delete this property?" class="text-gray-400 hover:text-red-200 group-hover:text-white transition-colors p-1">
                                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                 </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-400 text-sm italic">لا توجد وحدات لهذا المشروع.</p>
                            @endif
                        </div>
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
                     <h2 class="text-2xl font-bold text-gray-100">إضافة وحدة جديدة</h2>
                     <p class="text-gray-400 text-sm mt-1">قم بتعبئة البيانات التالية لإضافة وحدة جديدة للمشروع</p>
                </div>
               <div class="w-12 h-12 bg-[#498e49]/20 rounded-full flex items-center justify-center text-[#498e49]">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
               </div>
            </div>

            {{-- Errors --}}
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
                
                {{-- Basic Info --}}
                <div class="border-b border-neutral-700 pb-6">
                    <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                        البيانات الأساسية
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">المشروع التابع له</label>
                            <select wire:model="project_id" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                                <option value="">اختر المشروع</option>
                                @foreach (\App\Models\Project::get() as $project)
                                    <option value="{{$project->id}}">{{$project->name}}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">اسم الوحدة</label>
                            <input type="text" wire:model="name" placeholder="مثال: فيلا A1" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>

                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">السعر (ريال)</label>
                            <input type="number" wire:model="price" placeholder="0.00" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">عرض خاص (اختياري)</label>
                            <input type="text" autocomplete wire:model="offer" placeholder="مثال: خصم 599000 ريال" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">نوع العقار</label>
                             <select wire:model="type" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                                <option value="">اختر النوع</option>
                                <option value="فيلا">فيلا</option>
                                <option value="دور">دور</option>
                                <option value="شقة">شقة</option>
                            </select>
                        </div>

                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">الحالة</label>
                             <select wire:model="status" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                                <option value="جديد">جديد</option>
                                <option value="تحت الانشاء">تحت الانشاء</option>
                                <option value="مباع">مباع</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Specifications --}}
                <div class="border-b border-neutral-700 pb-6">
                    <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                        المواصفات
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">المساحة (م²)</label>
                             <input type="number" wire:model="area" placeholder="0" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">عدد الغرف</label>
                             <input type="number" wire:model="rooms" placeholder="0" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">دورات المياه</label>
                             <input type="number" wire:model="bathrooms" placeholder="0" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">الصالات</label>
                             <input type="number" wire:model="living_rooms" placeholder="0" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">غرف الماستر</label>
                             <input type="number" wire:model="mainds_room" placeholder="0" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">المداخل</label>
                             <input type="number" wire:model="doors" placeholder="0" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                    </div>
                </div>

                {{-- Features & Extras --}}
                 <div class="border-b border-neutral-700 pb-6">
                    <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                        المميزات والإضافات
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">مواقف السيارات</label>
                             <input type="number" wire:model="parkings" placeholder="0" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                         <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">غرفة حارس</label>
                             <input type="text" wire:model="driver_room" placeholder="نعم / لا" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">الواجهة</label>
                            <select wire:model="facade" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                                <option value="">اختر الواجهة</option>
                                <option value="شمالية">شمالية</option>
                                <option value="جنوبية">جنوبية</option>
                                <option value="شرقية">شرقية</option>
                                <option value="غربية">غربية</option>
                                <option value="شمالية غربية">شمالية غربية</option>
                                <option value="جنوبية غربية">جنوبية غربية</option>
                                <option value="شرقية شمالية">شرقية شمالية</option>
                                <option value="شرقية جنوبية">شرقية جنوبية</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-3 mt-4">
                             <input type="checkbox" id="furniture" wire:model="furniture" class="w-5 h-5 rounded border-neutral-600 bg-neutral-900 text-[#498e49] focus:ring-[#498e49] focus:ring-offset-neutral-900">
                            <label for="furniture" class="text-sm font-medium text-gray-300 cursor-pointer select-none"> الوحدة مؤثثة؟</label>
                        </div>
                    </div>
                </div>

                {{-- Images --}}
                <div class="pb-6">
                     <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                         صور الوحدة
                    </h3>
                    <div class="border-2 border-dashed border-neutral-600 rounded-2xl p-8 hover:border-[#498e49] hover:bg-[#498e49]/5 transition-all text-center cursor-pointer relative bg-neutral-900/50">
                         <input type="file" wire:model="photos" multiple accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                         <div class="flex flex-col items-center gap-2 text-gray-400">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-[#498e49]/50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                             </svg>
                             <span class="font-medium">اضغط هنا لرفع الصور</span>
                             <span class="text-xs text-gray-500">PNG, JPG up to 10MB</span>
                         </div>
                    </div>
                    @if ($photos)
                        <div class="flex flex-wrap gap-4 mt-4 pb-2 justify-center">
                             @foreach ($photos as $index => $photo)
                                <div class="relative w-24 h-24 rounded-xl overflow-hidden border border-neutral-600 group shrink-0 shadow-sm">
                                    <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                                    <button wire:click="removePhoto({{ $index }})" class="absolute top-1 right-1 bg-red-500/80 hover:bg-red-600 text-white rounded-full p-1 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Submit --}}
                <div class="pt-4 border-t border-neutral-700 flex justify-end">
                    <button wire:click="createProperties" wire:loading.attr="disabled" class="bg-[#498e49] hover:bg-[#3d7a3d] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-[#498e49]/20 transition-all flex items-center gap-2">
                         <span wire:loading.remove>حفظ الوحدة</span>
                         <span wire:loading>جاري الحفظ...</span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
