@props(['blog'])

<div x-data class="w-full md:w-[320px] p-2">
    <div x-on:click="navigateTo('{{ route('blog', $blog->id) }}');"
        class="group flex flex-col h-full bg-white rounded-[2rem] shadow-sm hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 overflow-hidden cursor-pointer border border-transparent hover:border-[#498e49]/30 text-right">

        {{-- Image Container --}}
        <div class="h-40 w-full overflow-hidden bg-gray-50 p-2">
            @php
                $image = $blog->image_blog ?? 'img/blog.jpg';
                $imageUrl = filter_var($image, FILTER_VALIDATE_URL)
                    ? $image
                    : (Str::contains($image, ['blogs/', 'uploads/'])
                        ? asset('storage/' . $image)
                        : asset(str_replace('\\', '', $image)));
            @endphp
            <img src="{{ $imageUrl }}"
                class="w-full h-full object-cover rounded-[1.5rem] transition-transform duration-700 group-hover:scale-105"
                alt="{{ $blog->title }}" onerror="this.src='{{ asset('img/blog.jpg') }}'">
        </div>

        {{-- Content --}}
        <div class="px-5 pb-5 pt-2 flex flex-col grow justify-between" dir="rtl">
            <h3
                class="text-lg font-bold text-gray-800 mb-3 leading-snug group-hover:text-[#498e49] transition-colors line-clamp-3">
                {{ $blog->title }}
            </h3>

            <div class="flex items-center gap-2 text-gray-400 text-xs font-medium mt-2 border-t border-gray-100 pt-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#498e49]" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span dir="ltr">{{ $blog->created_at->format('Y-m-d') }}</span>
            </div>
        </div>
    </div>
</div>
