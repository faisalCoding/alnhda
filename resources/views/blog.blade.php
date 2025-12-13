@extends('layouts.guest')

@section('title', $blog->title)

@section('main')
    <article class="w-full bg-[#fcfcfc] min-h-screen py-10 md:py-20" dir="rtl">
        <div class="container mx-auto px-4 lg:px-0 max-w-4xl">

            {{-- Breadcrumb / Back Link --}}
            <div class="mb-8 flex items-center text-sm text-gray-500">
                <a href="{{ route('blogs') }}" class="hover:text-[#498e49] flex items-center gap-1 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    العودة للمقالات
                </a>
                <span class="mx-2">/</span>
                <span class="text-gray-400 truncate max-w-[200px]">{{ $blog->title }}</span>
            </div>

            {{-- Article Header --}}
            <header class="mb-10 text-center">
                <div class="flex items-center justify-center gap-2 text-[#498e49] text-sm font-medium mb-4">
                    <span class="bg-[#498e49]/10 px-3 py-1 rounded-full">
                        {{ $blog->created_at->translatedFormat('d F Y') }}
                    </span>
                </div>

                <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold text-gray-900 leading-tight mb-8">
                    {{ $blog->title }}
                </h1>

                {{-- Featured Image --}}
                <div class="relative w-full aspect-video md:aspect-[21/9] rounded-3xl overflow-hidden shadow-2xl mb-12">
                    @php
                        $image = $blog->image_blog ?? 'img/blog.jpg';
                        $imageUrl = filter_var($image, FILTER_VALIDATE_URL)
                            ? $image
                            : (Str::contains($image, ['blogs/', 'uploads/'])
                                ? asset('storage/' . $image)
                                : asset(str_replace('\\', '', $image)));
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $blog->title }}"
                        class="absolute inset-0 w-full h-full object-cover"
                        onerror="this.src='{{ asset('img/blog.jpg') }}'">

                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent pointer-events-none"></div>
                </div>
            </header>

            {{-- Article Content --}}
            <div class="bg-white rounded-[2.5rem] p-6 md:p-12 shadow-sm border border-gray-100">
                <div class="prose prose-lg prose-green max-w-none text-gray-700 leading-loose font-light">
                    {!! $blog->content !!}
                </div>
            </div>

            {{-- Footer / Navigation --}}
            <div class="mt-12 flex justify-center">
                <a href="{{ route('blogs') }}"
                    class="group inline-flex items-center gap-2 bg-white border-2 border-[#498e49] text-[#498e49] hover:bg-[#498e49] hover:text-white px-8 py-3 rounded-xl font-bold transition-all duration-300 shadow-sm hover:shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 transform group-hover:translate-x-1 transition-transform" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    العودة لجميع المقالات
                </a>
            </div>

        </div>
    </article>
@endsection
