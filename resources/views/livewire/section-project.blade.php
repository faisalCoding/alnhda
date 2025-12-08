<div id="projects" class="  bg-[#eeeeee]  w-full flex text-center flex-col">
    <h1 class="text-3xl font-bold text-[#49A035] my-20">إكتشف مشاريعنا المتميزة</h1>
    <div
        class="container m-auto  w-full flex justify-center flex-wrap text-center flex-col gap-3 md:flex-row-reverse  md:items-stretch xl:justify-start ">

        @foreach (App\Models\Project::take(4)->get() as $project)
            <div class="flex flex-col items-center bg-white rounded-[40px] overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 md:w-[465px]">
                <img class="w-full h-72 object-cover rounded-t-[40px]" src="/storage/{{ $project->image_url }}" alt="{{ $project->name }}">
                <div class="p-6 w-full text-right "> {{-- Added padding and text-right for RTL --}}
                    <h2 class="text-3xl text-[#49A035] mb-3">{{ $project->name }}</h2> {{-- Increased font size, weight, and color --}}
                    <p class="text-gray-500 text-lg leading-relaxed mb-6 ">{{ $project->description }}</p> {{-- Adjusted text color, line height, and margin --}}
                    <div class="border-t border-gray-200 pt-6 space-y-4 *:flex-row-reverse"> {{-- Added border and spacing --}}
                        <div class="flex justify-between items-center "> {{-- Used justify-between for alignment --}}
                            <p class="text-lg font-semibold text-gray-700">عدد الوحدات</p>
                            <p class="text-xl text-[#498E49] font-bold">{{ $project->properties()->count() }}</p> {{-- Increased font size and weight --}}
                        </div>
                        <div class="flex justify-between items-center">
                            <p class="text-lg font-semibold text-gray-700">حالة المشروع</p>
                            <p class="text-xl text-[#498E49] font-bold">{{ $project->status }}</p>
                        </div>
                        <div class="flex justify-between items-center">
                            <p class="text-lg font-semibold text-gray-700">نوع المشروع</p>
                            <p class="text-xl text-[#498E49] font-bold">{{ $project->project_type }}</p>
                        </div>
                        <div class="flex justify-between items-center">
                            <p class="text-lg font-semibold text-gray-700">موقع المشروع</p>
                            <p class="text-xl text-[#498E49] font-bold">مدينة جدة {{ $project->location }}</p>
                        </div>
                    </div>
                    <button onclick="navigateTo('{{route('project', $project->id)}}');"
                        class="mt-8 w-full py-3 px-6 rounded-full text-white text-xl font-bold bg-[#49A035] hover:bg-[#3a802a] transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-[#49A035] focus:ring-opacity-50">
                        احجز الان
                    </button>
                </div>
            </div>
        @endforeach

    </div>
    <div class="container m-auto flex flex-row-reverse">
        <button onclick="navigateTo('{{route('projects')}}');"
            class="outline-btn">عرض
            الكل</button>
    </div>
</div>