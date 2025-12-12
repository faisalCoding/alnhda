@extends('layouts.guest')

@section('title', 'شروط الاستخدام')

@section('main')
    <div class="bg-gray-50 min-h-screen rtl" dir="rtl">
        {{-- Hero Section --}}
        <div class="relative bg-zinc-900 h-[200px] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent z-10"></div>
            <div
                class="absolute inset-0 opacity-20 bg-[url('https://images.unsplash.com/photo-1486312338219-ce68d2c6f44d?q=80&w=2672&auto=format&fit=crop')] bg-cover bg-center">
            </div>
            <div class="relative z-10 text-center px-4">
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">شروط الاستخدام</h1>
                <div class="w-16 h-1 bg-[#498E49] mx-auto rounded-full"></div>
            </div>
        </div>

        <div class="container mx-auto px-4 py-12">
            <div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-sm border border-gray-100 p-8 md:p-12">
                <div
                    class="prose prose-lg max-w-none prose-headings:text-gray-800 prose-p:text-gray-600 prose-strong:text-gray-900 prose-ul:text-gray-600">
                    <p class="lead">
                        مرحباً بك في موقع كيان النهضة العقارية. باستخدامك لهذا الموقع، فإنك توافق على الالتزام بشروط
                        الاستخدام التالية. يرجى قراءتها بعناية.
                    </p>

                    <h3>1. الملكية الفكرية</h3>
                    <p>
                        جميع المحتويات الموجودة على هذا الموقع، بما في ذلك النصوص والصور والشعارات والتصاميم، هي ملك لشركة
                        كيان النهضة العقارية أو مرخصيها ومحمية بموجب قوانين حقوق النشر والملكية الفكرية. لا يجوز نسخ أو
                        توزيع أو تعديل أي جزء من هذا الموقع دون إذن كتابي مسبق.
                    </p>

                    <h3>2. استخدام الموقع</h3>
                    <p>
                        تتعهد باستخدام هذا الموقع لأغراض مشروعة فقط. يمنع استخدام الموقع بأي طريقة قد تضر به أو تعطل توفره
                        للآخرين، أو لغرض نشر محتوى غير قانوني أو مسيء.
                    </p>

                    <h3>3. دقة المعلومات</h3>
                    <p>
                        نسعى جاهدين لضمان دقة المعلومات الواردة في الموقع (مثل تفاصيل العقارات والمشاريع)، ولكننا لا نقدم أي
                        ضمانات صريحة أو ضمنية بشأن اكتمالها أو دقتها. المعلومات خاضعة للتغيير دون إشعار مسبق.
                    </p>

                    <h3>4. روابط لطرف ثالث</h3>
                    <p>
                        قد يحتوي موقعنا على روابط لمواقع خارجية. نحن لسنا مسؤولين عن محتوى أو سياسات الخصوصية لتلك المواقع.
                        استخدامك لأي رابط خارجي يكون على مسؤوليتك الخاصة.
                    </p>

                    <h3>5. إخلاء المسؤولية</h3>
                    <p>
                        شركة كيان النهضة العقارية غير مسؤولة عن أي أضرار مباشرة أو غير مباشرة قد تنشأ عن استخدامك لهذا
                        الموقع أو عدم قدرتك على استخدامه.
                    </p>

                    <h3>6. التعديلات</h3>
                    <p>
                        نحتفظ بالحق في تعديل شروط الاستخدام هذه في أي وقت. استمرارك في استخدام الموقع بعد نشر التعديلات يعني
                        موافقتك عليها.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
