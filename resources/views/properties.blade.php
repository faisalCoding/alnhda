@extends('layouts.guest')

@section('title', 'KN | ' . $properties->name)

@section('main')
    <div class="container mx-auto px-4 py-8 rtl" dir="rtl">

        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
            <a href="{{ route('projects') }}" class="hover:text-[#498e49]">المشاريع</a>
            <span>/</span>
            <a href="{{ route('project', $properties->project->id) }}"
                class="hover:text-[#498e49]">{{ $properties->project->name }}</a>
            <span>/</span>
            <span class="text-gray-800 font-bold">{{ $properties->name }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Right Column (Main Info & Images) --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- Image Slider --}}
                <div class="w-full h-[400px] md:h-[500px] relative rounded-3xl overflow-hidden shadow-xl bg-gray-100 group">

                    @if ($properties->propertiesImages->count() > 0)
                        <div class="swiper mySwiper h-full w-full">
                            <div class="swiper-wrapper">
                                @foreach ($properties->propertiesImages as $image)
                                   <div class="swiper-slide">
                                        <img src="{{ asset('storage/' . $image->url) }}" alt="{{ $properties->name }}"
                                            class="w-full h-full object-cover">
                                    </div>
                                @endforeach
                            </div>
                            <div
                                class="swiper-button-next !text-white !w-10 !h-10 !bg-black/20 hover:!bg-black/50 !rounded-full !hidden group-hover:!flex after:!text-lg">
                            </div>
                            <div
                                class="swiper-button-prev !text-white !w-10 !h-10 !bg-black/20 hover:!bg-black/50 !rounded-full !hidden group-hover:!flex after:!text-lg">
                            </div>
                            <div class="swiper-pagination !bottom-4"></div>
                        </div>
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-500 flex-col gap-4">
                            <svg class="w-16 h-16 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                            <span>لا توجد صور للوحدة</span>
                        </div>
                    @endif

                    <div class="absolute top-4 right-4 z-10">
                        <span class="bg-[#498e49] text-white px-4 py-2 rounded-full font-bold shadow-lg">
                            {{ $properties->status }}
                        </span>
                    </div>
                </div>
                <div class="flex gap-4 items-center justify-center md:flex-row flex-col">
                    {{-- YouTube Video --}}
                    <div class="w-full">
                        <div class="flex items-center mb-6">
                            <div class="w-1.5 h-8 bg-[#498e49] rounded-full ml-3"></div>
                            <h2 class="text-2xl font-bold text-gray-800">فيديو الوحدة</h2>
                        </div>
                        <div
                            class="sm:w-full max-sm:w-full relative rounded-3xl overflow-hidden shadow-xl bg-gray-100 aspect-video">
                            <iframe class=" w-full h-full" src="{{ $properties->unit_youtube }}" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                    {{-- YouTube Video --}}
                    <div class="w-full">
                        <div class="flex items-center mb-6">
                            <div class="w-1.5 h-8 bg-[#498e49] rounded-full ml-3"></div>
                            <h2 class="text-2xl font-bold text-gray-800">فيديو مراحل انشاء الوحدة</h2>
                        </div>
                        <div
                            class="sm:w-full max-sm:w-full relative rounded-3xl overflow-hidden shadow-xl bg-gray-100 aspect-video">
                            <iframe class=" w-full h-full" src="{{ $properties->stages_building_youtube }}" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                </div>

                {{-- Description & Details --}}
                <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
                    <div class="flex items-center mb-6">
                        <div class="w-1.5 h-8 bg-[#498e49] rounded-full ml-3"></div>
                        <h2 class="text-2xl font-bold text-gray-800">تفاصيل الوحدة</h2>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-y-6 gap-x-4 text-center md:text-right">
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <span class="block text-gray-400 text-sm mb-1">نوع العقار</span>
                            <span class="font-bold text-gray-800">{{ $properties->type }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <span class="block text-gray-400 text-sm mb-1">المساحة</span>
                            <span class="font-bold text-gray-800">{{ $properties->area }} م²</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <span class="block text-gray-400 text-sm mb-1">السعر</span>
                            <span class="font-bold text-[#498e49] text-lg">{{ number_format($properties->price) }}
                                ريال</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <span class="block text-gray-400 text-sm mb-1">الغرف</span>
                            <span class="font-bold text-gray-800">{{ $properties->rooms }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <span class="block text-gray-400 text-sm mb-1">دورات المياه</span>
                            <span class="font-bold text-gray-800">{{ $properties->bathrooms }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <span class="block text-gray-400 text-sm mb-1">الصالات</span>
                            <span class="font-bold text-gray-800">{{ $properties->living_rooms }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <span class="block text-gray-400 text-sm mb-1">الماستر</span>
                            <span class="font-bold text-gray-800">{{ $properties->mainds_room }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <span class="block text-gray-400 text-sm mb-1">المداخل</span>
                            <span class="font-bold text-gray-800">{{ $properties->doors }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <span class="block text-gray-400 text-sm mb-1">الواجهة</span>
                            <span class="font-bold text-gray-800">{{ $properties->facade }}</span>
                        </div>
                    </div>
                </div>
                {{-- Description & Details --}}
                <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
                    <div class="flex items-center mb-6">
                        <div class="w-1.5 h-8 bg-[#498e49] rounded-full ml-3"></div>
                        <h2 class="text-2xl font-bold text-gray-800">المواد المستخدمة</h2>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-y-6 gap-x-4 text-center md:text-right">
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45"src="{{ url('/img/alfanar.png') }}" alt="">
                            <span class="block text-gray-600 text-md mb-1">المفاتيح والأفياش والطين والقواطع ضمان 25
                                سنة</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45"src="{{ url('/img/aljazira.png') }}" alt="">
                            <span class="block text-gray-600 text-md mb-1">الدهانات الخارجية</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45"src="{{ url('/img/fosroc.jpg') }}" alt="">
                            <span class="block text-gray-600 text-md mb-1">أرضيات المواقف</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45"src="{{ url('/img/jontun.png') }}" alt="">
                            <span class="block text-gray-600 text-md mb-1"> دهانات الداخلية</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45"src="{{ url('/img/polybit.jpg') }}" alt="">
                            <span class="block text-gray-600 text-md mb-1"> العازل الخارجي للخزانات</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45"src="{{ url('/img/saveto.png') }}" alt="">
                            <span class="block text-gray-600 text-md mb-1"> غراء البلاط الحوائط</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45"src="{{ url('/img/sika.png') }}" alt="">
                            <span class="block text-gray-600 text-md mb-1">العزل الداخلي لخزانات</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45"src="{{ url('/img/weber.jpg') }}" alt="">
                            <span class="block text-gray-600 text-md mb-1"> عازل أرضيات الحمامات</span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Left Column (Sidebar Info) --}}
            <div class="space-y-6">

                {{-- Features Card --}}
                <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                    <h3 class="font-bold text-gray-800 text-lg mb-4 border-b pb-2">مميزات إضافية</h3>
                    <ul class="space-y-3">
                        <li class="flex justify-between items-center">
                            <span class="text-gray-600">مواقف سيارات</span>
                            <span
                                class="font-bold text-gray-800 bg-gray-100 px-3 py-1 rounded-full text-sm">{{ $properties->parkings }}</span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span class="text-gray-600">غرفة سائق</span>
                            <span
                                class="font-bold text-gray-800 bg-gray-100 px-3 py-1 rounded-full text-sm">{{ $properties->driver_room }}</span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span class="text-gray-600">مؤثثة</span>
                            <span
                                class="font-bold {{ $properties->furniture ? 'text-[#498e49]' : 'text-gray-400' }} bg-gray-100 px-3 py-1 rounded-full text-sm">
                                {{ $properties->furniture ? 'نعم' : 'لا' }}
                            </span>
                        </li>
                        @if ($properties->offer)
                            <li class="flex flex-col gap-1 mt-4 p-3 bg-red-50 rounded-xl border border-red-100">
                                <span class="text-red-500 font-bold text-sm">عرض خاص</span>
                                <span class="font-bold text-red-700">{{ $properties->offer }} ريال</span>
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- Contact / Action Card (Placeholder) --}}
                <div
                    class="bg-gradient-to-br from-[#498e49] to-[#3d7a3d] rounded-2xl shadow-lg p-6 text-white text-center">
                    <h3 class="font-bold text-xl mb-2">مهتم بهذا العقار؟</h3>
                    <p class="text-white/80 text-sm mb-6">تواصل معنا الآن للحصول على مزيد من المعلومات أو لحجز موعد
                        للمعاينة.</p>
                    <a href="https://wa.me/966564364261?text=ارغب+بالاستفسار+عن+{{ $properties->name }}" target="_blank" rel="noopener noreferrer" 
                        class="w-full block bg-white text-[#498e49] font-bold py-3 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
                        تواصل معنا
                    </a>
                </div>

            </div>

        </div>

    </div>

    <!-- Swiper JS -->
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>

    <!-- Initialize Swiper -->
    <script>
        var swiper = new Swiper(".mySwiper", {
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
        });
    </script>
@endsection
