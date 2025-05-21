@extends('layouts.guest')

@section('title', 'KN | المشاريع')


@section('main')
<div id="projects" class="bg-[#EAF5EA] pb-10  w-full flex text-center flex-col flex-1">
    <h1 class="text-3xl font-bold text-[#49A035] my-20">إكتشف مشاريعنا المتميزة</h1>
    <div
        class="container m-auto  w-full flex flex-wrap text-center flex-col gap-3 md:flex-row-reverse  md:items-stretch xl:justify-start ">

        @foreach (App\Models\Project::get() as $project)
            <div class="flex flex-col items-center bg-white overflow-hidden md:w-[465px]  rounded-[40px]">
                <img class="w-full h-72 object-cover" src="/storage/{{ $project->image_url }}" alt="">
                <h1 class="text-2xl mx-2.5 mt-5">{{ $project->name }}</h1>
                <p class=" text-gray-500 text-lg mt-3.5 w-10/12">{{ $project->description }}</p>
                <div class="flex flex-col font-bold items-stretch w-10/12 pt-7">
                    <div class="flex flex-row-reverse py-2">
                        <p class="text-lg">عدد الوحدات</p>
                        <p class="text-lg text-[#498E49] pr-10 font-medium">
                            {{ $project->properties()->count() }}</p>
                    </div>
                    <div class="flex flex-row-reverse py-2">
                        <p class="text-lg">حالة المشروع</p>
                        <p class="text-lg text-[#498E49] pr-10 font-medium">{{ $project->status }}</p>
                    </div>
                    <div class="flex flex-row-reverse py-2">
                        <p class="text-lg">نوع المشروع</p>
                        <p class="text-lg text-[#498E49] pr-10 font-medium">{{ $project->project_type }}</p>
                    </div>
                    <div class="flex flex-row-reverse py-2">
                        <p class="text-lg">موقع المشروع</p>
                        <p class="text-lg  text-[#498E49] pr-10 font-medium">مدينة جدة {{ $project->location }}
                        </p>
                    </div>
                </div>
                <button onclick="navigateTo('{{route('project', $project->id)}}');"
                    class=" fill-btn">احجز
                    الان</button>
            </div>
        @endforeach

    </div>
</div>
@endsection

