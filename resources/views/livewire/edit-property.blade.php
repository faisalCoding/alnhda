<div class="w-full flex flex-col items-center gap-8 p-6" dir="rtl">

    {{-- Main Form Area --}}
    <div class="w-full lg:w-3/4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 p-8">

            {{-- Header --}}
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-100">تعديل الوحدة</h2>
                    <p class="text-gray-400 text-sm mt-1">تعديل بيانات الوحدة والمواصفات والصور</p>
                </div>
                <div class="w-12 h-12 bg-[#498e49]/20 rounded-full flex items-center justify-center text-[#498e49]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                </div>
            </div>

            {{-- Notifications --}}
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
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">المشروع التابع له</label>
                            <select wire:model="project_id"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                                <option value="">اختر المشروع</option>
                                @foreach (\App\Models\Project::get() as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">اسم الوحدة</label>
                            <input type="text" wire:model="name" placeholder="مثال: فيلا A1"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">السعر (ريال)</label>
                            <input type="number" wire:model="price" placeholder="0.00"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">عرض خاص (اختياري)</label>
                            <input type="text" autocomplete wire:model="offer" placeholder="مثال: خصم 599000 ريال"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">نوع العقار</label>
                            <select wire:model="type"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                                <option value="">اختر النوع</option>
                                <option value="فيلا">فيلا</option>
                                <option value="دور">دور</option>
                                <option value="شقة">شقة</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">الحالة</label>
                            <select wire:model="status"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
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
                            <input type="number" wire:model="area" placeholder="0"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">عدد الغرف</label>
                            <input type="number" wire:model="rooms" placeholder="0"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">دورات المياه</label>
                            <input type="number" wire:model="bathrooms" placeholder="0"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">الصالات</label>
                            <input type="number" wire:model="living_rooms" placeholder="0"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">غرف الماستر</label>
                            <input type="number" wire:model="mainds_room" placeholder="0"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">المداخل</label>
                            <input type="number" wire:model="doors" placeholder="0"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">يوتيوب الوحدة</label>
                            <input type="text" wire:model="unit_youtube" placeholder="0"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">يوتيوب المبنى</label>
                            <input type="text" wire:model="stages_building_youtube" placeholder="0"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
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
                            <input type="number" wire:model="parkings" placeholder="0"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">غرفة حارس</label>
                            <input type="text" wire:model="driver_room" placeholder="نعم / لا"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 placeholder-neutral-500 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-gray-400">الواجهة</label>
                            <select wire:model="facade"
                                class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
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
                            <input type="checkbox" id="furniture" wire:model="furniture"
                                class="w-5 h-5 rounded border-neutral-600 bg-neutral-900 text-[#498e49] focus:ring-[#498e49] focus:ring-offset-neutral-900">
                            <label for="furniture"
                                class="text-sm font-medium text-gray-300 cursor-pointer select-none"> الوحدة
                                مؤثثة؟</label>
                        </div>
                    </div>
                </div>

                {{-- Images --}}
                <div class="pb-6">
                    <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#498e49]"></span>
                        صور الوحدة
                    </h3>

                    {{-- Existing Images --}}
                    <h4 class="text-gray-400 text-sm mb-2">الصور الحالية</h4>
                    <div class="flex flex-wrap gap-4 mb-6">
                        @forelse ($propertyModel->propertiesImages as $img)
                            <div
                                class="relative w-24 h-24 rounded-xl overflow-hidden border border-neutral-600 group shrink-0 shadow-sm">
                                <img src="{{ asset('storage/' . $img->url) }}" class="w-full h-full object-cover">
                                <button wire:click="deleteImage({{ $img->id }})"
                                    wire:confirm="هل أنت متأكد من حذف هذه الصورة؟"
                                    class="absolute top-1 right-1 bg-red-500/80 hover:bg-red-600 text-white rounded-full p-1 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm italic">لا توجد صور حالية</p>
                        @endforelse
                    </div>


                    {{-- Upload New --}}
                    <h4 class="text-gray-400 text-sm mb-2">إضافة صور جديدة</h4>
                    <div
                        class="border-2 border-dashed border-neutral-600 rounded-2xl p-8 hover:border-[#498e49] hover:bg-[#498e49]/5 transition-all text-center cursor-pointer relative bg-neutral-900/50">
                        <input type="file" wire:model="photos" multiple accept="image/*"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <div class="flex flex-col items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-[#498e49]/50"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="font-medium">اضغط هنا لإضافة صور جديدة</span>
                            <span class="text-xs text-gray-500">PNG, JPG up to 10MB</span>
                        </div>
                    </div>
                    @if ($photos)
                        <div class="flex flex-wrap gap-4 mt-4 pb-2 justify-center">
                            @foreach ($photos as $index => $photo)
                                <div
                                    class="relative w-24 h-24 rounded-xl overflow-hidden border border-neutral-600 group shrink-0 shadow-sm">
                                    <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                                    <button wire:click="removeNewPhoto({{ $index }})"
                                        class="absolute top-1 right-1 bg-red-500/80 hover:bg-red-600 text-white rounded-full p-1 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Submit --}}
                <div class="pt-4 border-t border-neutral-700 flex justify-end gap-3">
                    <a href="{{ route('projects-dashboard') }}"
                        class="bg-neutral-700 hover:bg-neutral-600 text-white px-8 py-3 rounded-xl font-bold transition-all">
                        إلغاء
                    </a>

                    <button wire:click="updateProperty" wire:loading.attr="disabled"
                        class="bg-[#498e49] hover:bg-[#3d7a3d] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-[#498e49]/20 transition-all flex items-center gap-2">
                        <span wire:loading.remove>حفظ التعديلات</span>
                        <span wire:loading>جاري الحفظ...</span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
