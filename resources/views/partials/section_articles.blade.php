@php
    $articles = App\Models\Article::latest()->take(6)->get();
@endphp

@if ($articles->isNotEmpty())
    <section class="w-full bg-[#f5fdf5] py-16 md:py-24" dir="rtl" aria-labelledby="articles-heading">
        <div class="container mx-auto px-4 md:px-6">

            <div class="mb-12 text-center">
                <span class="inline-block rounded-full border border-[#498E49] px-4 py-1 text-sm font-medium text-[#498E49]">
                    المدوّنة
                </span>
                <h2 id="articles-heading" class="mt-4 text-3xl font-bold leading-tight text-gray-900 md:text-4xl">
                    اكتشف احدث المقالات
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg leading-relaxed text-gray-600">
                    نصائح ومقالات من فريق كيان النهضة العقارية عن شراء الفلل والشقق في جدة، ومراحل البناء،
                    والاستثمار العقاري في السعودية.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-article-card :article="$article" />
                @endforeach
            </div>

            <div class="mt-12 flex justify-center">
                <a href="{{ route('articles') }}"
                    onclick="event.preventDefault(); navigateTo('{{ route('articles') }}');"
                    class="group inline-flex items-center gap-2 rounded-xl border-2 border-[#498E49] bg-white px-8 py-3 font-bold text-[#498E49] shadow-sm transition-all duration-300 hover:bg-[#498E49] hover:text-white hover:shadow-lg">
                    كل المقالات العقارية
                    <svg class="h-5 w-5 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
            </div>

        </div>
    </section>
@endif
