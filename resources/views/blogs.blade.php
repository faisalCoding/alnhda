@extends('layouts.guest')

@section('title', 'KN | المقالات')


@section('main')

<div class="bg-[#EAF5EA] w-full flex flex-col justify-center grow flex-1">
    <h1 class="text-3xl font-bold text-[#49A035] my-20 text-center">اكتشف احدث المقالات</h1>
    <div
        class=" container pb-10 m-auto w-full flex flex-col flex-wrap justify-center items-stretch gap-4 md:flex-row-reverse  md:items-stretch xl:justify-start ">
        @foreach (App\Models\Blog::get() as $blog)
            <x-blog-card :blog="$blog"/>
        @endforeach
    </div>

</div>
@endsection

