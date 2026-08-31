@extends('layouts.guest')

@php
    $seoAuto = app(\App\Services\SeoRecordDefaults::class)->for($collection);
    $items = $collection->items->filter(fn ($entry) => $entry->item !== null);
@endphp

@section('title', $seoAuto['title'])
@section('description', $seoAuto['description'])
@section('image', $seoAuto['image'])
@section('og_type', $seoAuto['og_type'])

@push('jsonld')
    @php
        $collectionSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $collection->title,
            'description' => $seoAuto['description'],
            'url' => url()->current(),
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $items->count(),
                'itemListElement' => $items->values()->map(fn ($entry, $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => \App\Services\LinkTargets::nameFor($entry->item),
                    'url' => \App\Services\LinkTargets::urlFor($entry->item),
                ])->all(),
            ],
        ];
    @endphp
    <script type="application/ld+json">
        {!! json_encode($collectionSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('main')
    <div class="w-full bg-[#fcfcfc] min-h-screen py-10 md:py-20" dir="rtl">
        <div class="container mx-auto px-4 lg:px-0 max-w-6xl">

            <header class="mb-12 text-center">
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold text-gray-900 leading-tight">
                    {{ $collection->title }}
                </h1>

                @if ($collection->description)
                    <p class="mx-auto mt-6 max-w-3xl text-lg font-light leading-loose text-gray-600">
                        {{ $collection->description }}
                    </p>
                @endif
            </header>

            @if ($items->isEmpty())
                <p class="text-center text-gray-400">لا يوجد محتوى في هذه الصفحة بعد.</p>
            @else
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $entry)
                        <x-collection-item-card :item="$entry->item" :key="'item-' . $entry->id" />
                    @endforeach
                </div>
            @endif

            <div class="mt-16 flex justify-center">
                <a href="{{ route('projects') }}"
                    class="group inline-flex items-center gap-2 bg-white border-2 border-[#498e49] text-[#498e49] hover:bg-[#498e49] hover:text-white px-8 py-3 rounded-xl font-bold transition-all duration-300 shadow-sm hover:shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 transform group-hover:translate-x-1 transition-transform" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    تصفّح كل المشاريع
                </a>
            </div>

        </div>
    </div>
@endsection
