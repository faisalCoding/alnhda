@extends('layouts.guest')

@section('title', 'KN | ' . $properties->name)

@php
    $unitDescriptionParts = array_filter([
        $properties->type ? $properties->name . ' - ' . $properties->type : $properties->name,
        $properties->project?->location ? 'في ' . $properties->project->location : null,
        $properties->rooms ? $properties->rooms . ' غرف' : null,
        $properties->bathrooms ? $properties->bathrooms . ' دورات مياه' : null,
        $properties->area ? 'بمساحة ' . $properties->area . ' م²' : null,
        $properties->status ?: null,
    ]);
    $unitDescription = \Illuminate\Support\Str::limit(implode('، ', $unitDescriptionParts) . '.', 155);
@endphp

@section('description', $unitDescription)

@section('image', $properties->propertiesImages->first() ? asset('storage/' . $properties->propertiesImages->first()->url) : asset('img/KNicon.png'))

@push('jsonld')
    @php
        $unitImage = $properties->propertiesImages->first()
            ? asset('storage/' . $properties->propertiesImages->first()->url)
            : asset('img/KNicon.png');
        $unitSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $properties->name,
            'image' => $unitImage,
            'description' => $unitDescription,
            'brand' => ['@type' => 'Brand', 'name' => 'كيان النهضة العقارية'],
        ];
        if (! empty($properties->price)) {
            $unitSchema['offers'] = [
                '@type' => 'Offer',
                'price' => (string) $properties->price,
                'priceCurrency' => 'SAR',
                'availability' => 'https://schema.org/InStock',
                'url' => url()->current(),
            ];
        }
        $unitBreadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'المشاريع', 'item' => route('projects')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $properties->project->name, 'item' => route('project', $properties->project->id)],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $properties->name, 'item' => url()->current()],
            ],
        ];
    @endphp
    <script type="application/ld+json">
        {!! json_encode($unitSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode($unitBreadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('main')
    <div x-data="{ showModal: false }" class="container mx-auto px-4 py-8 rtl" dir="rtl">

        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
            <a href="{{ route('projects') }}" class="hover:text-[#498e49]">المشاريع</a>
            <span>/</span>
            <a href="{{ route('project', $properties->project->id) }}"
                class="hover:text-[#498e49]">{{ $properties->project->name }}</a>
            <span>/</span>
            <span class="text-gray-800 font-bold">{{ $properties->name }}</span>
        </div>

        {{-- Page Title --}}
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6">
            {{ $properties->name }}
            <span class="text-gray-500 font-medium text-xl">— {{ $properties->project->name }}</span>
        </h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Right Column (Main Info & Images) --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- Image Slider --}}
                <div class="w-full h-[400px] md:h-[500px] relative rounded-3xl overflow-hidden shadow-xl bg-gray-100 group">

                    @if ($properties->propertiesImages->count() > 0)
                        <div class="swiper mySwiper h-full w-full">
                            <div class="swiper-wrapper">
                                @foreach ($properties->propertiesImages as $image)
                                   <div class="swiper-slide cursor-zoom-in" 
                                         @click="showModal = true; $nextTick(() => { lightboxSwiper.slideTo({{ $loop->index }}, 0); lightboxSwiper.update(); })">
                                         <img src="{{ asset('storage/' . $image->url) }}" alt="{{ $properties->name }}"
                                             class="w-full h-full object-cover">
                                     </div>
                                @endforeach
                            </div>
                            <div
                                class="swiper-button-next !text-white !w-10 !h-10 !bg-black/20 hover:!bg-black/50 !rounded-full !hidden group-hover:!flex *:!w-3">
                            </div>
                            <div
                                class="swiper-button-prev !text-white !w-10 !h-10 !bg-black/20 hover:!bg-black/50 !rounded-full !hidden group-hover:!flex *:!w-3">
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
                @if (!empty($properties->unit_youtube) || !empty($properties->stages_building_youtube))
                    <div class="flex gap-4 items-center justify-center md:flex-row flex-col">
                        {{-- YouTube Video - Unit --}}
                        @if (!empty($properties->unit_youtube))
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
                        @endif

                        {{-- YouTube Video - Stages --}}
                        @if (!empty($properties->stages_building_youtube))
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
                        @endif
                    </div>
                @endif

                {{-- Action Buttons --}}
                @if (!empty($properties->pdf_path) || !empty($properties->project->map_url))
                    <div class="flex flex-wrap gap-4 items-center mb-6">
                        @if (!empty($properties->pdf_path))
                            <a href="{{ route('properties.download', $properties) }}"
                                class="flex items-center justify-center gap-3 bg-white text-[#498e49] border border-[#498e49] hover:bg-[#498e49] hover:text-white transition-all duration-300 px-6 py-3.5 rounded-2xl font-bold shadow-sm hover:shadow-md transform hover:-translate-y-0.5 w-full sm:w-auto">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                <span>تحميل ملف عرض الوحدة (PDF)</span>
                            </a>
                        @endif

                        @if (!empty($properties->project->map_url))
                            <a href="{{ $properties->project->map_url }}" target="_blank"
                                class="flex items-center justify-center gap-3 bg-[#498e49] text-white hover:bg-[#3d7a3d] transition-all duration-300 px-6 py-3.5 rounded-2xl font-bold shadow-sm hover:shadow-md transform hover:-translate-y-0.5 w-full sm:w-auto">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span>خريطة المشروع (Google Maps)</span>
                            </a>
                        @endif
                    </div>
                @endif

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
                            <img class=" max-h-45" src="{{ url('/img/alfanar.webp') }}" alt="شركة الفنار" width="800" height="227">
                            <span class="block text-gray-600 text-md mb-1">المفاتيح والأفياش والطين والقواطع ضمان 25
                                سنة</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45" src="{{ url('/img/aljazira.webp') }}" alt="شركة الجزيرة" width="800" height="223">
                            <span class="block text-gray-600 text-md mb-1">الدهانات الخارجية</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45" src="{{ url('/img/fosroc.webp') }}" alt="شركة فوسروك" width="709" height="768">
                            <span class="block text-gray-600 text-md mb-1">أرضيات المواقف</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45" src="{{ url('/img/jontun.webp') }}" alt="شركة جونتون" width="800" height="244">
                            <span class="block text-gray-600 text-md mb-1"> دهانات الداخلية</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45" src="{{ url('/img/polybit.webp') }}" alt="شركة بوليبيت" width="511" height="111">
                            <span class="block text-gray-600 text-md mb-1"> العازل الخارجي للخزانات</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45" src="{{ url('/img/saveto.webp') }}" alt="شركة سافيتو" width="600" height="600">
                            <span class="block text-gray-600 text-md mb-1"> غراء البلاط الحوائط</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45" src="{{ url('/img/sika.webp') }}" alt="شركة سيكا" width="800" height="693">
                            <span class="block text-gray-600 text-md mb-1">العزل الداخلي لخزانات</span>
                        </div>
                        <div class="p-4 flex items-stretch justify-between bg-gray-50  flex-col rounded-xl">
                            <img class=" max-h-45" src="{{ url('/img/weber.webp') }}" alt="شركة ويبر" width="800" height="281">
                            <span class="block text-gray-600 text-md mb-1"> عازل أرضيات الحمامات</span>
                        </div>
                    </div>
                </div>

                {{-- Guarantees Section --}}
                @if (!empty($properties->project->guarantees))
                    <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
                        <div class="flex items-center mb-6">
                            <div class="w-1.5 h-8 bg-[#498e49] rounded-full ml-3"></div>
                            <h2 class="text-2xl font-bold text-gray-800">ضمانات المشروع</h2>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($properties->project->guarantees as $guarantee)
                                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                    <div class="w-10 h-10 rounded-full bg-[#498e49]/10 flex items-center justify-center text-[#498e49] flex-shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-gray-700 font-medium text-base leading-relaxed">{{ $guarantee }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

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
                    <a href="https://wa.me/966564364261?text=ارغب+بالاستفسار+عن+{{ $properties->name }}" id="partial_contact_us" target="_blank" rel="noopener noreferrer" 
                        class="w-full block bg-white text-[#498e49] font-bold py-3 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
                        تواصل معنا
                    </a>
                </div>

            </div>

        </div>

        {{-- Image Modal --}}
        <div x-show="showModal" 
             style="display: none;"
             class="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 p-4 backdrop-blur-sm"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @keydown.escape.window="showModal = false"
             @close-lightbox.window="showModal = false">
            
            {{-- Close Button --}}
            <button @click="showModal = false" class="absolute top-6 right-6 text-white/70 hover:text-white transition-colors z-[120] p-2 bg-white/10 hover:bg-white/20 rounded-full backdrop-blur-md">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            {{-- Swiper Container --}}
            <div @click.outside="showModal = false" class="relative max-w-7xl max-h-[90vh] w-full h-full flex items-center justify-center">
                <div class="swiper lightboxSwiper w-full h-full" dir="ltr" style="--swiper-theme-color: #498e49; --swiper-pagination-bullet-inactive-color: #ffffff; --swiper-pagination-bullet-inactive-opacity: 0.4;">
                    <div class="swiper-wrapper">
                        @foreach ($properties->propertiesImages as $image)
                            <div class="swiper-slide flex items-center justify-center select-none">
                                <img src="{{ asset('storage/' . $image->url) }}" 
                                     class="max-w-full max-h-[85vh] object-contain rounded-2xl shadow-2xl pointer-events-none" 
                                     draggable="false" 
                                     alt="{{ $properties->name }}">
                            </div>
                        @endforeach
                    </div>

                    {{-- Navigation Controls --}}
                    <button class="lightbox-next absolute right-4 top-1/2 -translate-y-1/2 z-[110] text-white/70 hover:text-white transition-colors p-2 bg-white/10 hover:bg-white/20 rounded-full backdrop-blur-md select-none">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                    <button class="lightbox-prev absolute left-4 top-1/2 -translate-y-1/2 z-[110] text-white/70 hover:text-white transition-colors p-2 bg-white/10 hover:bg-white/20 rounded-full backdrop-blur-md select-none">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>

                    {{-- Pagination --}}
                    <div class="lightbox-pagination absolute bottom-4 left-0 right-0 flex justify-center z-[110]"></div>
                </div>
            </div>
        </div>

    </div>

    <!-- Swiper JS -->
    <script src="{{ asset('js/swiper-bundle.min.js') }}"></script>

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

        var lightboxSwiper = new Swiper(".lightboxSwiper", {
            observer: true,
            observeParents: true,
            pagination: {
                el: ".lightbox-pagination",
                clickable: true,
                dynamicBullets: true,
            },
            navigation: {
                nextEl: ".lightbox-next",
                prevEl: ".lightbox-prev",
            },
            keyboard: {
                enabled: true,
            },
            loop: false,
            on: {
                click(swiper, event) {
                    if (event.target.classList.contains('swiper-slide')) {
                        window.dispatchEvent(new CustomEvent('close-lightbox'));
                    }
                }
            }
        });
    </script>
@endsection
