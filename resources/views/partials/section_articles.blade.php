<div class="bg-[#EAF5EA] w-full flex flex-col justify-center items-stretch">
    <h1 class="text-3xl font-bold text-[#49A035] my-20 text-center">اكتشف احدث المقالات</h1>
    <div
        class=" container m-auto w-full flex flex-col flex-wrap justify-center items-stretch gap-4 md:flex-row-reverse  md:items-stretch ">
        @foreach (App\Models\Article::take(10)->get() as $article)
            <x-article-card :article="$article" />
        @endforeach
    </div>

    <div class="container m-auto flex flex-row-reverse">
        <button onclick="navigateTo('{{ route('articles') }}');"
            class=" bg-[#ffffff00] text-[#498E49] border-[#498E49] border text-base text-center
             rounded-full py-3.5 px-10 self-start m-7 cursor-pointer hover:bg-[#386e38] hover:text-white">عرض
            الكل</button>
    </div>
</div>
