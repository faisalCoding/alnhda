<section class="py-16 md:py-24 bg-white overflow-hidden" dir="rtl">
    <div class="container mx-auto px-4 md:px-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

            {{-- Text Content --}}
            <div class="space-y-8" data-aos="fade-left">
                <div class="space-y-4">
                    <span
                        class="inline-block px-4 py-1 rounded-full border border-[#498E49] text-[#498E49] text-sm font-medium">
                        من نحن
                    </span>
                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 leading-tight">
                        الشريك الأمثل في التطوير <br class="hidden lg:block"> العقاري والحلول الإنشائية
                    </h2>
                    <p class="text-gray-600 text-lg leading-relaxed text-justify">
                        نفتخر في شركة النهضة كوننا من الشركات الرائدة في عالم التطوير العقاري، حيث نمتلك رؤية واضحة
                        ترتكز على الابتكار والجودة في جميع مراحل العمل. نسعى لتقديم مشروعات عقارية متكاملة تعكس أعلى
                        معايير التصميم والهندسة، وتلبي تطلعات العملاء والمستثمرين. منذ انطلاقتنا وضعنا نصب أعيننا بناء
                        الثقة مع عملائنا عبر الالتزام بالمواعيد والجودة الفائقة.
                    </p>
                </div>

                <div>
                    <a href="{{ route('about-us') }}" class="fill-btn inline-flex items-center gap-2 group">
                        <span>تعرف على المزيد</span>
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 transform transition-transform group-hover:-translate-x-1" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Image Content --}}
            <div class="relative" data-aos="fade-right">
                <div class="relative rounded-2xl overflow-hidden shadow-2xl">
                    {{--  Placeholder image if no specific one exists.  --}}
                    <img src="https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?q=80&w=2070&auto=format&fit=crop"
                        alt="مبنى عصري"
                        class="w-full h-[500px] object-cover hover:scale-105 transition-transform duration-700">

                    {{-- Overlay Gradient --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                </div>

                {{-- Floating Card / Secondary Element --}}
                <div
                    class="hidden md:flex absolute -bottom-10 -left-10 bg-white p-6 rounded-xl shadow-xl max-w-xs items-center gap-4 animate-bounce-slow">
                    <div class="bg-[#498E49]/10 p-3 rounded-full text-[#498E49]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900 text-lg">خبرة عريقة</h4>
                        <p class="text-sm text-gray-500">سنوات من التميز في البناء</p>
                    </div>
                </div>
            </div>

        </div>

        {{-- Features Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-20">
            {{-- Feature 1 --}}
            <div class="bg-gray-50 p-8 rounded-2xl text-center group hover:bg-[#498E49] transition-colors duration-300">
                <div
                    class="w-16 h-16 mx-auto bg-white rounded-full flex items-center justify-center text-[#498E49] mb-4 shadow-sm group-hover:scale-110 transition-transform">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-white">ابتكار دائم</h3>
                <p class="text-gray-500 text-sm leading-relaxed group-hover:text-white/90">
                    نقدم حلولاً هندسية مبدعة تضيف قيمة حقيقية وتميز مشاريعنا في السوق.
                </p>
            </div>

            {{-- Feature 2 --}}
            <div class="bg-gray-50 p-8 rounded-2xl text-center group hover:bg-[#498E49] transition-colors duration-300">
                <div
                    class="w-16 h-16 mx-auto bg-white rounded-full flex items-center justify-center text-[#498E49] mb-4 shadow-sm group-hover:scale-110 transition-transform">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-white">حلول متكاملة</h3>
                <p class="text-gray-500 text-sm leading-relaxed group-hover:text-white/90">
                    نوفر خدمات شاملة تشمل التصميم والتنفيذ والإشراف لضمان مشروع متكامل.
                </p>
            </div>

            {{-- Feature 3 --}}
            <div class="bg-gray-50 p-8 rounded-2xl text-center group hover:bg-[#498E49] transition-colors duration-300">
                <div
                    class="w-16 h-16 mx-auto bg-white rounded-full flex items-center justify-center text-[#498E49] mb-4 shadow-sm group-hover:scale-110 transition-transform">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-white">خبرة راسخة</h3>
                <p class="text-gray-500 text-sm leading-relaxed group-hover:text-white/90">
                    نمتلك تاريخاً طويلاً في تنفيذ المشاريع العقارية والإنشائية بمعايير جودة عالمية.
                </p>
            </div>

        </div>
    </div>
</section>
