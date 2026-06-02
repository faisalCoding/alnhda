@extends('layouts.guest')

@section('header')
    {{-- override header to hid it --}}
@stop
@section('title', 'تطوير عقاري وفلل وشقق سكنية فاخرة')
@section('description', 'شركة كيان النهضة العقارية - روّاد التطوير العقاري في المملكة العربية السعودية. نقدم أرقى الفلل والحلول السكنية والاستثمارية بأعلى معايير الجودة والضمانات. تصفح مشاريعنا المتميزة الآن.')
@section('main')
    <div class=" w-full bg-emerald-50 flex flex-col ">

        @include('partials.header')
        @include('partials.about-section')
        @livewire('section-project')
        @include('partials.section_articles')
        {{-- footer in layouts guest --}}
    </div>
@endsection
