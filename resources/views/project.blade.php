@extends('layouts.guest')

@section('title', 'KN | المشاريع')


@section('main')
    <div id="projects" class="bg-[#EAF5EA] pb-10  w-full flex text-center flex-col flex-1">
        <div class="relative h-50 flex items-end justify-center overflow-hidden">
            <img class="w-full object-cover absolute z-1" src="/storage/{{ $project->image_url }}" alt="">
            <h1 class="text-xl relative font-bold text-white mb-10 z-2">{{ $project->name }}</h1>
        </div>
       

    

    <style>
        /* يمكنك إضافة بعض التنسيقات الأساسية هنا أو استخدام Tailwind */

        /* تنسيق حاوية السلايدر */
        .mySwiperContainer {
            width: 90%; /* عرض السلايدر */
            max-width: 800px; /* أقصى عرض للسلايدر */
            height: 450px; /* ارتفاع السلايدر، يمكنك تعديله */
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* تنسيق الشرائح والصور بداخلها */
        .swiper-slide {
            text-align: center;
            font-size: 18px;
            background: #fff; /* خلفية للشريحة */

            /* لتوسيط المحتوى إذا كان نصًا */
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .swiper-slide img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover; /* لجعل الصورة تملأ الشريحة مع الحفاظ على النسبة وقص الزائد */
        }

        /* تخصيص ألوان أسهم التنقل والـ pagination (اختياري) */
        :root {
            --swiper-navigation-color: #ffffff; /* لون أسهم التنقل */
            --swiper-pagination-color: #ffffff; /* لون نقاط الـ pagination */
        }
         .swiper-button-next,
         .swiper-button-prev {
            background-color: rgba(0,0,0,0.3);
            padding: 20px; /* لتكبير مساحة الضغط قليلاً */
            border-radius: 50%;
            width: 20px !important; /* لتصغير حجم السهم نفسه */
            height: 20px !important;
        }
        .swiper-button-next::after,
        .swiper-button-prev::after {
            font-size: 16px !important; /* حجم أيقونة السهم */
        }

    </style>

    <div class="swiper mySwiperContainer">
        <div class="swiper-wrapper">
            <div class="swiper-slide">
                <img src="https://placehold.co/800x450/E91E63/FFFFFF?text=صورة+1" alt="صورة 1">
            </div>
            <div class="swiper-slide">
                <img src="https://placehold.co/800x450/3F51B5/FFFFFF?text=صورة+2" alt="صورة 2">
            </div>
            <div class="swiper-slide">
                <img src="https://placehold.co/800x450/4CAF50/FFFFFF?text=صورة+3" alt="صورة 3">
            </div>
            <div class="swiper-slide">
                <img src="https://placehold.co/800x450/FF9800/FFFFFF?text=صورة+4" alt="صورة 4">
            </div>
            </div>

        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>

        <div class="swiper-pagination"></div>
    </div>

    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const swiper = new Swiper('.mySwiperContainer', {
                // === الخيارات الأساسية ===
                direction: 'horizontal', // اتجاه السلايدر (horizontal أو vertical)
                loop: true,              // لتكرار الشرائح بشكل لا نهائي
                speed: 600,              // سرعة الانتقال بين الشرائح (بالمللي ثانية)
                grabCursor: true,        // يظهر مؤشر يد عند المرور فوق السلايدر للإشارة إلى إمكانية السحب

                // === (اختياري) أزرار التنقل ===
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },

                // === (اختياري) نقاط الـ Pagination ===
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true, // لجعل النقاط قابلة للنقر للانتقال بين الشرائح
                    dynamicBullets: true, // لجعل النقاط ديناميكية (تظهر نقاط أقل وتتحرك)
                },

                // === (اختياري) التشغيل التلقائي ===
                // autoplay: {
                //   delay: 3000, // الوقت بين كل شريحة وأخرى (3 ثواني)
                //   disableOnInteraction: false, // يستمر التشغيل التلقائي حتى بعد تفاعل المستخدم
                // },

                // === (اختياري) تأثيرات الانتقال ===
                // effect: 'fade', // تأثير التلاشي (يحتاج إلى CSS إضافي بسيط إذا لم يكن مدعومًا بالكامل)
                // fadeEffect: {
                //    crossFade: true
                // },

                // effect: 'cube',
                // cubeEffect: {
                //   shadow: true,
                //   slideShadows: true,
                //   shadowOffset: 20,
                //   shadowScale: 0.94,
                // },

                // effect: 'coverflow',
                // coverflowEffect: {
                //   rotate: 50,
                //   stretch: 0,
                //   depth: 100,
                //   modifier: 1,
                //   slideShadows: true,
                // },

                // effect: 'flip',
                // flipEffect: {
                //   slideShadows: true,
                //   limitRotation: true,
                // },

                // === (اختياري) لجعله متجاوبًا مع أحجام الشاشات المختلفة ===
                // breakpoints: {
                //   // عندما يكون عرض الشاشة >= 320px
                //   320: {
                //     slidesPerView: 1, // عدد الشرائح الظاهرة
                //     spaceBetween: 10  // المسافة بين الشرائح
                //   },
                //   // عندما يكون عرض الشاشة >= 768px
                //   768: {
                //     slidesPerView: 2,
                //     spaceBetween: 20
                //   },
                //   // عندما يكون عرض الشاشة >= 1024px
                //   1024: {
                //     slidesPerView: 3,
                //     spaceBetween: 30
                //   }
                // }
            });
        });
    </script>




        <div
            class="container m-auto  w-full flex flex-wrap text-center flex-col gap-3 md:flex-row-reverse  md:items-stretch xl:justify-start ">

        </div>
    </div>
@endsection
