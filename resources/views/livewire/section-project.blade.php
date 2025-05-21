<div id="projects" class="  bg-[#eeeeee]  w-full flex text-center flex-col">
    <h1 class="text-3xl font-bold text-[#49A035] my-20">إكتشف مشاريعنا المتميزة</h1>
    <div
        class="container m-auto  w-full flex justify-center flex-wrap text-center flex-col gap-3 md:flex-row-reverse  md:items-stretch xl:justify-start ">

        @foreach (App\Models\Project::take(4)->get() as $project)
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

                <a  href="{{route('project', $project->id)}}" wire:navigate.hover class="fill-btn"> احجز الان</a>

            </div>
        @endforeach

    </div>
    <div class="container m-auto flex flex-row-reverse">
        <button onclick="navigateTo('{{route('projects')}}');"
            class="outline-btn">عرض
            الكل</button>
    </div>
</div>