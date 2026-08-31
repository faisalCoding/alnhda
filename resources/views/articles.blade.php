@extends('layouts.guest')

@section('main')

    <div class="bg-[#f5fdf5] w-full flex flex-col justify-center grow flex-1">
        <h1 class="mb-10 mt-14 text-center text-3xl font-bold text-[#49A035]">اكتشف احدث المقالات</h1>
        <div class="container mx-auto grid w-full grid-cols-1 gap-6 px-4 pb-16 md:grid-cols-2 lg:grid-cols-3" dir="rtl">
            @foreach (App\Models\Article::latest()->get() as $article)
                <x-article-card :article="$article" :eager="$loop->first" />
            @endforeach
        </div>

    </div>
@endsection
