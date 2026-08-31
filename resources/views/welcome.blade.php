@extends('layouts.guest')

@php
    $facts = app(\App\Services\HomeFacts::class);
@endphp

@push('jsonld')
    @php
        $homeProjects = $facts->showsSection('projects') ? \App\Models\Project::ordered()->get() : collect();

        $projectListSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            '@id' => url('/') . '#projects',
            'name' => 'مشاريع كيان النهضة العقارية في جدة',
            'numberOfItems' => $homeProjects->count(),
            'itemListElement' => $homeProjects
                ->values()
                ->map(fn ($project, $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $project->name,
                    'url' => route('project', $project),
                ])
                ->all(),
        ];

        // Structured data has to describe what the page shows. A section
        // switched off in the panel takes its markup with it rather than
        // promising a crawler something no reader can find.
        $faqEntries = $facts->showsSection('faq') ? $facts->faq() : [];

        $faqSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            '@id' => url('/') . '#faq',
            'inLanguage' => 'ar',
            'mainEntity' => collect($faqEntries)
                ->map(fn (array $entry): array => [
                    '@type' => 'Question',
                    'name' => $entry['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $entry['answer']],
                ])
                ->all(),
        ];
    @endphp
    @if ($homeProjects->isNotEmpty())
        <script type="application/ld+json">
            {!! json_encode($projectListSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
    @if ($faqEntries)
        <script type="application/ld+json">
            {!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
@endpush

@section('main')
    <div class=" w-full bg-emerald-50 flex flex-col ">

        @include('partials.header')

        @if ($facts->showsSection('about'))
            @include('partials.about-section')
        @endif

        @if ($facts->showsSection('projects'))
            @livewire('section-project')
        @endif

        @if ($facts->showsSection('districts'))
            @include('partials.districts-section')
        @endif

        @if ($facts->showsSection('guarantees'))
            @include('partials.guarantees-section')
        @endif

        @if ($facts->showsSection('articles'))
            @include('partials.section_articles')
        @endif

        @if ($facts->showsSection('faq'))
            @include('partials.faq-section')
        @endif
        {{-- footer in layouts guest --}}
    </div>

    @livewire('contact-wizard')
@endsection
