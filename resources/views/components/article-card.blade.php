@props(['article'])

<div x-data class="w-full max-w-2xl">
    @php
        $image = $article->image_article ?? 'img/article.jpg';
        $imageUrl = filter_var($image, FILTER_VALIDATE_URL)
            ? $image
            : (Str::contains($image, ['articles/', 'uploads/'])
                ? asset('storage/' . $image)
                : asset(str_replace('\\', '', $image)));
    @endphp

    <div x-on:click="navigateTo('{{ route('article', $article->id) }}');"
        class="group flex flex-row-reverse h-36 bg-white shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden cursor-pointer border border-transparent hover:border-[#498e49]/30"
        dir="rtl">

        {{-- Content — left three quarters --}}
        <div class="flex flex-col justify-between px-5 py-4 grow text-right">
            <h3
                class="text-base font-bold text-gray-800 leading-snug group-hover:text-[#498e49] transition-colors line-clamp-3">
                {{ $article->title }}
            </h3>

            <div class="flex items-center gap-2 text-gray-400 text-xs font-medium border-t border-gray-100 pt-3 mt-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#498e49]" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span dir="ltr">{{ $article->created_at->format('Y-m-d') }}</span>
            </div>
        </div>
        {{-- Image — right quarter --}}
        <div class="relative w-1/4 shrink-0 overflow-hidden">
            <img src="{{ $imageUrl }}" class="absolute inset-0 w-full h-full object-cover grayscale"
                alt="{{ $article->title }}" onerror="this.src='{{ asset('img/article.jpg') }}'">
            {{-- Green overlay --}}
            <div class="absolute inset-0 bg-[#498e49]/70 mix-blend-multiply"></div>
        </div>
    </div>
</div>
