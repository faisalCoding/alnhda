@extends('layouts.guest')

@section('title', 'سياسة الخصوصية')

@section('main')
    <div class="bg-gray-50 min-h-screen rtl" dir="rtl">
        {{-- Hero Section --}}
        <div class="relative bg-zinc-900 h-[200px] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent z-10"></div>
            <div
                class="absolute inset-0 opacity-20 bg-[url('https://images.unsplash.com/photo-1450101499163-c8848c66ca85?q=80&w=2670&auto=format&fit=crop')] bg-cover bg-center">
            </div>
            <div class="relative z-10 text-center px-4">
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">سياسة الخصوصية</h1>
                <div class="w-16 h-1 bg-[#498E49] mx-auto rounded-full"></div>
            </div>
        </div>

        <div class="container mx-auto px-4 py-12">
            <div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-sm border border-gray-100 p-8 md:p-12">
                <div
                    class="prose prose-lg max-w-none prose-headings:text-gray-800 prose-p:text-gray-600 prose-strong:text-gray-900 prose-ul:text-gray-600">
                    <p class="lead">
                        في كيان النهضة العقارية، نولي أهمية قصوى لخصوصية زوارنا وعملائنا. توضح سياسة الخصوصية هذه كيفية
                        جمعنا واستخدامنا وحمايتنا لمعلوماتك الشخصية.
                    </p>

                    <h3>1. المعلومات التي نجمعها</h3>
                    <p>
                        نقوم بجمع المعلومات التي تقدمها لنا مباشرة، مثل عند ملء نموذج الاتصال أو الاشتراك في نشرتنا
                        البريدية. قد تشمل هذه المعلومات:
                    </p>
                    <ul>
                        <li>الاسم</li>
                        <li>عنوان البريد الإلكتروني</li>
                        <li>رقم الهاتف</li>
                        <li>أي معلومات أخرى تقرر تقديمها</li>
                    </ul>

                    <h3>2. كيف نستخدم معلوماتك</h3>
                    <p>
                        نستخدم المعلومات التي نجمعها من أجل:
                    </p>
                    <ul>
                        <li>الرد على استفساراتكم وتقديم الدعم.</li>
                        <li>إرسال تحديثات وعروض خاصة (إذا اشتركت في النشرة البريدية).</li>
                        <li>تحسين خدماتنا وموقعنا الإلكتروني.</li>
                    </ul>

                    <h3>3. مشاركة المعلومات</h3>
                    <p>
                        نحن لا نبيع أو نؤجر معلوماتك الشخصية لأطراف ثالثة. قد نشارك معلوماتك فقط مع مزودي الخدمة الموثوقين
                        الذين يساعدوننا في تشغيل موقعنا أو إدارة أعمالنا (مثل خدمات البريد الإلكتروني)، بشرط موافقتهم على
                        الحفاظ على سرية هذه المعلومات.
                    </p>

                    <h3>4. حماية المعلومات</h3>
                    <p>
                        نحن نتخذ إجراءات أمنية مناسبة لحماية معلوماتك الشخصية من الوصول غير المصرح به أو التغيير أو الإفشاء
                        أو الإتلاف.
                    </p>

                    <h3>5. التغييرات على سياسة الخصوصية</h3>
                    <p>
                        قد نقوم بتحديث سياسة الخصوصية هذه من وقت لآخر. سيتم نشر أي تغييرات على هذه الصفحة، لذا نرجو مراجعتها
                        بانتظام.
                    </p>

                    <h3>6. اتصل بنا</h3>
                    <p>
                        إذا كان لديك أي أسئلة حول سياسة الخصوصية هذه، يرجى التواصل معنا عبر صفحة <a
                            href="{{ route('contact-us') }}" class="text-[#498E49] hover:underline">تواصل معنا</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
