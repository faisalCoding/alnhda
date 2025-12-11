<div id="projects" class="bg-[#f8f9fa] w-full flex text-center flex-col py-16 rtl" dir="rtl">
    <div class="relative mb-12">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900">إكتشف مشاريعنا <span class="text-[#49A035]">المتميزة</span></h1>
        <div class="w-24 h-1.5 bg-[#49A035] mx-auto mt-4 rounded-full opacity-80"></div>
    </div>
    
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach (App\Models\Project::take(4)->get() as $project)
                <div class="group bg-white rounded-[2rem] overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col h-full">
                    {{-- Image Section --}}
                    <div class="relative h-60 overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent z-10 opacity-60"></div>
                        <img class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700" 
                             src="/storage/{{ $project->image_url }}" 
                             alt="{{ $project->name }}">
                        
                        <div class="absolute top-4 right-4 z-20">
                             <span class="bg-white/90 backdrop-blur-sm text-[#49A035] text-xs font-bold px-3 py-1.5 rounded-full shadow-sm">
                                {{ $project->status }}
                            </span>
                        </div>
                        
                        <div class="absolute bottom-4 right-4 z-20 text-white text-right">
                            <h2 class="text-xl font-bold mb-1 shadow-black/10 drop-shadow-md">{{ $project->name }}</h2>
                            <p class="text-gray-200 text-xs flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                جدة - {{ $project->location }}
                            </p>
                        </div>
                    </div>
                    
                    {{-- Content Section --}}
                    <div class="p-5 flex flex-col flex-grow">
                        {{-- Stats Grid --}}
                        <div class="grid grid-cols-2 gap-3 mb-5">
                            <div class="bg-gray-50 rounded-2xl p-3 text-center hover:bg-[#49A035]/5 transition-colors group/stat">
                                <span class="block text-gray-400 text-xs mb-1">الوحدات</span>
                                <span class="block text-[#49A035] font-bold text-lg group-hover/stat:scale-110 transition-transform">{{ $project->properties()->count() }}</span>
                            </div>
                            <div class="bg-gray-50 rounded-2xl p-3 text-center hover:bg-[#49A035]/5 transition-colors group/stat">
                                <span class="block text-gray-400 text-xs mb-1">النوع</span>
                                <span class="block text-gray-800 font-bold text-sm mt-1 whitespace-nowrap overflow-hidden text-ellipsis">{{ $project->project_type }}</span>
                            </div>
                        </div>

                        <p class="text-gray-500 text-sm leading-relaxed mb-6 line-clamp-2 min-h-[2.5rem] text-right">
                            {{ $project->description }}
                        </p>

                        <button onclick="navigateTo('{{route('project', $project->id)}}');"
                            class="mt-auto w-full py-3 rounded-xl text-white font-bold bg-[#49A035] hover:bg-[#3d8b2a] active:scale-95 transition-all duration-200 shadow-md shadow-[#49A035]/20 flex items-center justify-center gap-2 group/btn">
                            <span>عرض التفاصيل</span>
                            <svg class="w-4 h-4 transform group-hover/btn:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-center mt-12">
            <button onclick="navigateTo('{{route('projects')}}');"
                class="group bg-white text-gray-600 border border-gray-200 px-8 py-3 rounded-full font-bold hover:border-[#49A035] hover:text-[#49A035] transition-all duration-300 shadow-sm hover:shadow-md flex items-center gap-2">
                <span>عرض جميع المشاريع</span>
                <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </button>
        </div>
    </div>
</div>