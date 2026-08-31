@props(['item'])

@php
    $seo = app(\App\Services\SeoRecordDefaults::class)->for($item);
    $url = \App\Services\LinkTargets::urlFor($item);
    $kind = match (true) {
        $item instanceof \App\Models\Project => 'مشروع',
        $item instanceof \App\Models\Properties => 'وحدة',
        default => 'مقال',
    };
@endphp

<a href="{{ $url }}"
    class="group flex flex-col overflow-hidden rounded-3xl bg-white shadow-sm hover:shadow-xl border border-gray-100 hover:border-[#498e49]/30 transition-all duration-300"
    dir="rtl">

    <div class="relative aspect-video overflow-hidden bg-gray-100">
        <img src="{{ $seo['image'] }}" alt="{{ \App\Services\LinkTargets::nameFor($item) }}"
            class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy" width="800" height="450"
            onerror="this.src='{{ asset('img/article.webp') }}'">
        <span
            class="absolute top-4 right-4 rounded-full bg-white/90 backdrop-blur-md px-3 py-1 text-xs font-bold text-[#498e49] shadow-sm">
            {{ $kind }}
        </span>
    </div>

    <div class="flex flex-1 flex-col gap-2 p-6 text-right">
        <h3 class="text-xl font-bold text-gray-800 leading-snug transition-colors group-hover:text-[#498e49] line-clamp-2">
            {{ \App\Services\LinkTargets::nameFor($item) }}
        </h3>

        @if ($seo['description'])
            <p class="text-sm font-light leading-relaxed text-gray-500 line-clamp-3">{{ $seo['description'] }}</p>
        @endif
    </div>
</a>
