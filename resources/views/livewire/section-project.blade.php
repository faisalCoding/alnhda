<div id="projects" class="bg-[#f8f9fa] w-full flex text-center flex-col py-16 rtl" dir="rtl">
    <div class="relative mb-12">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900">إكتشف مشاريعنا <span
                class="text-[#49A035]">المتميزة</span></h1>
        <div class="w-24 h-1.5 bg-[#49A035] mx-auto mt-4 rounded-full opacity-80"></div>
    </div>

    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach (App\Models\Project::take(4)->get() as $project)
                <div onclick="navigateTo('{{ route('project', $project->id) }}');"
                    class="group relative rounded-[2rem] overflow-hidden h-[500px] cursor-pointer shadow-lg hover:shadow-2xl transition-all duration-500">

                    {{-- Background Image --}}
                    <img class="absolute inset-0 w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700"
                        src="/storage/{{ $project->image_url }}" alt="{{ $project->name }}">

                    {{-- Gradient Overlay --}}
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300">
                    </div>

                    {{-- Top Logo/Status --}}
                    <div class="absolute top-6 right-6 z-10">
                        <span
                            class="bg-white/90 backdrop-blur-md text-[#498E49] text-xs font-bold px-4 py-2 rounded-full shadow-sm">
                            {{ $project->status }}
                        </span>
                    </div>

                    {{-- Content Bottom --}}
                    <div class="absolute bottom-0 left-0 right-0 p-8 z-20 flex flex-col justify-end h-full">

                        <div
                            class="transform translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
                            {{-- Title --}}
                            <h2 class="text-3xl font-bold text-white mb-2 leading-tight">{{ $project->name }}</h2>

                            {{-- Location --}}
                            <div class="flex items-center gap-2 text-gray-300 text-sm mb-4">
                                <svg class="w-4 h-4 text-[#498E49]" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>جدة - {{ $project->location }}</span>
                            </div>

                            {{-- Description (Reveals on Hover) --}}
                            <p
                                class="text-gray-300 text-sm leading-relaxed line-clamp-2 mb-6 opacity-0 max-h-0 group-hover:opacity-100 group-hover:max-h-20 transition-all duration-500 delay-100 ease-out">
                                {{ $project->description }}
                            </p>

                            {{-- Action Bar --}}
                            <div class="flex items-center justify-between pt-4 border-t border-white/10">
                                <span class="text-white font-medium text-sm">عرض التفاصيل</span>
                                <div
                                    class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-white group-hover:bg-[#498E49] transition-colors duration-300 backdrop-blur-sm">
                                    <svg class="w-5 h-5 transform rotate-180" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-center mt-12">
            <button onclick="navigateTo('{{ route('projects') }}');"
                class="group bg-white text-[#498E49] border border-[#498E49] px-8 py-3 rounded-full font-bold hover:bg-[#498E49] hover:text-white transition-all duration-300 shadow-sm hover:shadow-md flex items-center gap-2">
                <span>عرض جميع المشاريع</span>
                <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </button>
        </div>
    </div>
</div>
