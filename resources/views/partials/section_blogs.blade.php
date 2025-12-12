<div class="bg-[#EAF5EA] w-full flex flex-col justify-center items-stretch">
    <h1 class="text-3xl font-bold text-[#49A035] my-20 text-center">اكتشف احدث المقالات</h1>
    <div
        class=" container m-auto w-full flex flex-col flex-wrap justify-center items-stretch gap-4 md:flex-row-reverse  md:items-stretch xl:justify-start ">
        @foreach (App\Models\Blog::take(5)->get() as $blog)
            <div
                class="flex flex-col items-center text-right  bg-white overflow-hidden  rounded-[64px] shadow-lg cursor-pointer md:min-w-xl ease-in-out duration-85 hover:scale-95 hover:border hover:border-green-500">
                <img class="w-13 object-cover self-end mr-10 mt-10" src="/img/blog_icon.jpg" alt="">
                <h1 class="text-xl font-bold mx-2.5 mt-1 w-10/12">{{ $blog->title }}</h1>
                <p class=" text-gray-500 text-lg mt-3.5 w-10/12 mb-6">{{ substr($blog->content, -60) }} . . .
                </p>

                <div class=" text-[#23BA5B] text-lg text-center  self-start mb-7 ml-8 cursor-pointer">
                    {{ $blog->created_at }}</div>
            </div>
        @endforeach
    </div>

    <div class="container m-auto flex flex-row-reverse">
        <button onclick="navigateTo('{{ route('blogs') }}');"
            class=" bg-[#ffffff00] text-[#498E49] border-[#498E49] border text-base text-center
             rounded-full py-3.5 px-10 self-start m-7 cursor-pointer hover:bg-[#386e38] hover:text-white">عرض
            الكل</button>
    </div>
</div>
