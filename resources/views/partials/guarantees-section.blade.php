@php
    $guarantees = app(\App\Services\HomeFacts::class)->guarantees();
@endphp

@if ($guarantees)
    <section class="bg-white w-full py-16 md:py-24" dir="rtl" aria-labelledby="guarantees-heading">
        <div class="container mx-auto px-4 md:px-6">

            <div class="mb-12 text-center">
                <span class="inline-block px-4 py-1 rounded-full border border-[#498E49] text-[#498E49] text-sm font-medium">
                    ضماناتنا
                </span>
                <h2 id="guarantees-heading" class="mt-4 text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                    ما نضمنه لك مكتوبًا مع كل وحدة
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-gray-600 text-lg leading-relaxed">
                    لا نكتفي بتسليم الوحدة. كل مشروع من مشاريع كيان النهضة العقارية في جدة يُسلَّم بضمانات
                    مكتوبة تغطي الهيكل والتمديدات، وتفاصيل ضمان كل مشروع مذكورة في صفحته.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($guarantees as $guarantee)
                    <div class="flex items-start gap-4 rounded-2xl border border-gray-100 bg-[#f8fbf8] p-6 shadow-sm">
                        <span class="shrink-0 rounded-full bg-[#498E49]/10 p-3 text-[#498E49]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                            </svg>
                        </span>
                        <p class="text-gray-800 text-lg font-medium leading-relaxed">{{ $guarantee }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('projects') }}"
                    class="inline-flex items-center gap-2 text-[#498E49] font-bold hover:underline">
                    تصفّح مشاريع كيان النهضة العقارية في جدة
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
            </div>

        </div>
    </section>
@endif
