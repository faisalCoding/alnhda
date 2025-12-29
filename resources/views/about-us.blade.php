@extends('layouts.guest')

@section('title', 'من نحن')
@section('description', 'كيان النهضة العقارية - خبرة 40 عاماً في التطوير العقاري والهندسة المعمارية في المملكة العربية
    السعودية.')

@section('main')
    <div class="bg-gray-50 min-h-screen rtl" dir="rtl">
        {{-- Hero Section --}}
        <div class="relative bg-zinc-900 h-[300px] flex items-center justify-center overflow-hidden">
            <div
                class="absolute inset-0 opacity-20 bg-[url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?q=80&w=2670&auto=format&fit=crop')] bg-cover bg-center">
            </div>
            <div class="relative z-10 text-center px-4">
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">من نحن</h1>
                <div class="w-20 h-1 bg-[#498E49] mx-auto rounded-full"></div>
            </div>
        </div>

        <div class="container mx-auto px-4 py-16">
            {{-- Main Content --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center mb-20">
                <div class="space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-[#498E49]/10 flex items-center justify-center text-[#498E49]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900">كيان النهضة العقارية</h2>
                    </div>

                    <p class="text-gray-600 leading-8 text-lg text-justify">
                        نحن في كيان النهضة العقارية نفتخر بمسيرة حافلة تمتد لأكثر من <span
                            class="text-[#498E49] font-bold">40 عاماً</span> من الخبرة والتميز في سوق المملكة العربية
                        السعودية، تحديداً في مدينة جدة. تخصصنا يجمع بين <span class="font-bold text-gray-800">التطوير
                            العقاري</span> و <span class="font-bold text-gray-800">الهندسة المعمارية</span>، لتقديم حلول
                        سكنية واستثمارية ترتقي بجودة الحياة وتواكب تطلعات عملائنا.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-8">
                        <div
                            class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 transform hover:-translate-y-1 transition-transform duration-300">
                            <h3 class="text-4xl font-bold text-[#498E49] mb-2">40+</h3>
                            <p class="text-gray-600 font-medium">سنة من الخبرة</p>
                        </div>
                        <div
                            class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 transform hover:-translate-y-1 transition-transform duration-300">
                            <h3 class="text-4xl font-bold text-[#498E49] mb-2">100%</h3>
                            <p class="text-gray-600 font-medium">رضا العملاء</p>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div class="absolute -inset-4 bg-[#498E49]/10 rounded-3xl -rotate-3 z-0"></div>
                    <img src="{{asset('img/rebarandplan.jpg')}}"
                        alt="مشاريع كيان النهضة" class="relative z-10 w-full h-[500px] object-cover rounded-2xl shadow-lg">
                </div>
            </div>

            {{-- Values Section --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="w-14 h-14 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">الجودة والتميز</h3>
                    <p class="text-gray-600 leading-relaxed">
                        نلتزم بأعلى معايير الجودة في جميع مشاريعنا، من التصميم المعماري الدقيق إلى التنفيذ المتقن، لضمان
                        استدامة وتميز عقاراتنا.
                    </p>
                </div>

                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <div
                        class="w-14 h-14 bg-[#498E49]/10 rounded-full flex items-center justify-center text-[#498E49] mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">الابتكار والتطوير</h3>
                    <p class="text-gray-600 leading-relaxed">
                        نواكب أحدث التطورات في عالم البناء والتصميم المعماري لنقدم مشاريع عصرية تلبي احتياجات الحياة الحديثة
                        وتطلعات المستقبل.
                    </p>
                </div>

                <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="w-14 h-14 bg-orange-50 rounded-full flex items-center justify-center text-orange-600 mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">العميل أولاً</h3>
                    <p class="text-gray-600 leading-relaxed">
                        نضع رضا عملائنا في مقدمة أولوياتنا، ونسعى لبناء علاقات طويلة الأمد مبنية على الثقة والشفافية
                        والاحترافية في التعامل.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
