@props(['article', 'eager' => false])

@php
    $image = $article->image_article ?? 'img/article.webp';
    $imageUrl = filter_var($image, FILTER_VALIDATE_URL)
        ? $image
        : (Str::contains($image, ['articles/', 'uploads/', 'blogs/'])
            ? asset('storage/' . $image)
            : asset(str_replace('\\', '', $image)));
    $url = route('article', $article->id);
@endphp

<a href="{{ $url }}" x-data x-on:click.prevent="navigateTo('{{ $url }}')"
    class="group flex h-full flex-col overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:border-[#498e49]/30 hover:shadow-xl"
    dir="rtl">

    {{-- The photograph is the reason someone stops at a card, so it is shown
         rather than greyed out behind a colour wash. --}}
    <div class="relative aspect-video overflow-hidden bg-gray-100">
        <img src="{{ $imageUrl }}" alt="{{ $article->title }}"
            class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            onerror="this.src='{{ asset('img/article.webp') }}'" width="800" height="450"
            loading="{{ $eager ? 'eager' : 'lazy' }}" @if ($eager) fetchpriority="high" @endif>
    </div>

    <div class="flex flex-1 flex-col gap-3 p-6 text-right">
        <div class="flex items-center gap-2 text-xs font-medium text-gray-400">
            <svg class="h-4 w-4 text-[#498e49]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
            <span>{{ $article->created_at->translatedFormat('d F Y') }}</span>
            <span class="text-gray-300">•</span>
            <span>{{ $article->readingTimeLabel() }}</span>
        </div>

        <h3 class="line-clamp-2 text-xl font-bold leading-snug text-gray-800 transition-colors group-hover:text-[#498e49]">
            {{ $article->title }}
        </h3>

        <p class="line-clamp-3 text-sm font-light leading-relaxed text-gray-500">{{ $article->excerpt() }}</p>

        <span class="mt-auto inline-flex items-center gap-1.5 pt-2 text-sm font-bold text-[#498e49]">
            اقرأ المقال
            <svg class="h-4 w-4 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24"
                stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </span>
    </div>
</a>
