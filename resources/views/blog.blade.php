@extends('layouts.guest')

@section('title', 'KN | المقالات')


@section('main')

    <div class="bg-[#EAF5EA] w-full flex flex-col justify-center grow flex-1">
        <h1 class="text-3xl font-bold text-[#49A035] my-20 text-center">اكتشف احدث المقالات</h1>
        <div
            class=" container pb-10 m-auto w-full flex flex-col flex-wrap justify-center items-stretch gap-4 md:flex-row-reverse  md:items-stretch xl:justify-start ">

            <div
                class="flex flex-col items-center text-right  bg-white overflow-hidden  rounded-[64px] shadow-lg cursor-pointer md:min-w-xl ease-in-out duration-85 hover:scale-95 hover:border hover:border-green-500">
                <img class="w-13 object-cover self-end mr-10 mt-10" src="/img/blog_icon.jpg" alt="">
                <h1 class="text-xl font-bold mx-2.5 mt-1 w-10/12">{{ $blog->title }}</h1>
                <p class=" text-gray-500 text-lg mt-3.5 w-10/12 mb-6">{!! $blog->content !!}
                </p>

                <div class=" text-[#23BA5B] text-lg text-center  self-start mb-7 ml-8 cursor-pointer">
                    {{ $blog->created_at }}</div>

            </div>

        </div>

    </div>




@endsection
