@extends('layouts.guest')

@section('title', 'تواصل معنا')
@section('description',
    'تواصل مع كيان النهضة العقارية للاستفسار عن مشاريعنا وخدماتنا في جدة، المملكة العربية
    السعودية.')

@section('main')
    <div class="bg-gray-50 min-h-screen rtl" dir="rtl">
        {{-- Hero Section --}}
        <div class="relative bg-zinc-900 h-[300px] flex items-center justify-center overflow-hidden">
            <div
                class="absolute inset-0 opacity-20 bg-[url('https://images.unsplash.com/photo-1554469384-e58fac16e23a?q=80&w=2670&auto=format&fit=crop')] bg-cover bg-center">
            </div>
            <div class="relative z-10 text-center px-4">
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">تواصل معنا</h1>
                <div class="w-20 h-1 bg-[#498E49] mx-auto rounded-full"></div>
            </div>
        </div>

        <div class="container mx-auto px-4 py-16">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    {{-- Contact Info Cards --}}
                    <div class="lg:col-span-1 space-y-6">
                        {{-- Address --}}
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-start gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-[#498E49]/10 flex items-center justify-center text-[#498E49] flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg mb-1">موقعنا</h3>
                                <p class="text-gray-600">المملكة العربية السعودية <br> مدينة جدة</p>
                            </div>
                        </div>

                        {{-- Phone --}}
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-start gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-[#498E49]/10 flex items-center justify-center text-[#498E49] flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg mb-1">اتصل بنا</h3>
                                <a href="tel:966564364261"
                                    class="text-gray-600 hover:text-[#498E49] transition-colors dir-ltr block text-right">056
                                    436 4261</a>
                            </div>
                        </div>

                        {{-- Email --}}
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-start gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-[#498E49]/10 flex items-center justify-center text-[#498E49] flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg mb-1">البريد الإلكتروني</h3>
                                <a href="mailto:info@kayanalnhda.com"
                                    class="text-gray-600 hover:text-[#498E49] transition-colors">info@kayanalnhda.com</a>
                            </div>
                        </div>
                    </div>

                    {{-- Contact Form --}}
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
                            <h2 class="text-2xl font-bold text-gray-900 mb-6">أرسل لنا رسالة</h2>
                            <form action="#" method="POST" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">الاسم
                                            الكامل</label>
                                        <input type="text" id="name" name="name"
                                            class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:outline-none focus:border-[#498E49] focus:ring-1 focus:ring-[#498E49] transition-colors">
                                    </div>
                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">رقم
                                            الهاتف</label>
                                        <input type="tel" id="phone" name="phone"
                                            class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:outline-none focus:border-[#498E49] focus:ring-1 focus:ring-[#498E49] transition-colors">
                                    </div>
                                </div>
                                <div>
                                    <label for="subject"
                                        class="block text-sm font-medium text-gray-700 mb-2">الموضوع</label>
                                    <input type="text" id="subject" name="subject"
                                        class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:outline-none focus:border-[#498E49] focus:ring-1 focus:ring-[#498E49] transition-colors">
                                </div>
                                <div>
                                    <label for="message"
                                        class="block text-sm font-medium text-gray-700 mb-2">الرسالة</label>
                                    <textarea id="message" name="message" rows="4"
                                        class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:outline-none focus:border-[#498E49] focus:ring-1 focus:ring-[#498E49] transition-colors"></textarea>
                                </div>
                                <div>
                                    <button type="button"
                                        class="bg-[#498E49] hover:bg-[#3d7a3d] text-white font-bold py-4 px-8 rounded-xl transition-all duration-300 transform hover:-translate-y-1 shadow-lg hover:shadow-xl w-full md:w-auto">
                                        إرسال الرسالة
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Map --}}
                <div class="mt-12">
                    <div class="w-full h-[400px] rounded-3xl overflow-hidden shadow-sm border border-gray-100">
                        <iframe
                            src="https://maps.google.com/maps?q=جامع%20الانصار%20حي%20الواحة%20جدة@21.5660262,39.2480873&z=15&output=embed&z=17"
                            width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
