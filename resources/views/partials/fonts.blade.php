{{-- خط الموقع: Lama Sans، مستضاف على هذا النطاق.

     يُعلَن هنا في رأس الصفحة لا داخل حزمة Vite، لسببين: خادم التطوير لا يخدم
     public/ فينتهي مسار ‎/fonts/…‎ داخل الحزمة إلى 404 أثناء العمل، ثم إن
     الإعلان في الرأس يبدأ التحميل دون انتظار تحليل ملف الأنماط.

     تُشحن الأوزان التي يستعملها الموقع فعلاً وحدها — كل وزن ملف يُنزَّل —
     ولا تُشحن المائلة: لا مكان لها في نص عربي، ولا يستعملها الموقع أصلاً. --}}

{{-- متن الصفحة يُرسم بالوزن العادي، فيُطلب مع الصفحة لا بعد أنماطها. --}}
<link rel="preload" href="/fonts/lama-sans/lama-sans-400.woff2" as="font" type="font/woff2" crossorigin>

<style>
    @foreach ([300, 400, 500, 600, 700, 800] as $weight)
        @font-face {
            font-family: 'Lama Sans';
            font-style: normal;
            font-weight: {{ $weight }};
            font-display: swap;
            src: url('/fonts/lama-sans/lama-sans-{{ $weight }}.woff2') format('woff2');
        }
    @endforeach
</style>
