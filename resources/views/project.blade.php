@extends('layouts.guest')

@section('title', 'KN | ' . $project->name)

@section('main')
    <div class="container mx-auto px-4 py-8 rtl" dir="rtl">

        {{-- Image Section --}}
        <div class="w-full h-[500px] relative rounded-3xl overflow-hidden mb-8 shadow-xl">
             @if($project->image_url)
                <img src="{{ asset('storage/' . $project->image_url) }}" alt="{{ $project->name }}" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-500 text-xl">
                    لا توجد صورة للمشروع
                </div>
            @endif
            <div class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-black/80 via-black/40 to-transparent p-10">
                <h1 class="text-4xl font-bold text-white mb-2 shadow-sm">{{ $project->name }}</h1>
            </div>
        </div>

        {{-- Description Section --}}
        <div class="bg-white rounded-2xl shadow-sm p-8 mb-8 border border-gray-100">
            <div class="flex items-center mb-6">
                 <div class="w-1.5 h-8 bg-[#498e49] rounded-full ml-3"></div>
                <h2 class="text-2xl font-bold text-gray-800">وصف المشروع</h2>
            </div>
            <p class="text-gray-600 leading-loose whitespace-pre-line text-lg text-justify">
                {{ $project->description }}
            </p>
        </div>

        {{-- Project Details Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Type -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center hover:shadow-md transition-shadow">
                <span class="text-gray-400 text-sm mb-2">نوع المشروع</span>
                <span class="text-xl font-bold text-[#498e49]">{{ $project->project_type }}</span>
            </div>
            
            <!-- Status -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center hover:shadow-md transition-shadow">
                <span class="text-gray-400 text-sm mb-2">الحالة</span>
                <span class="text-xl font-bold {{ $project->status == 'جديد' ? 'text-emerald-600' : 'text-orange-600' }}">
                    {{ $project->status }}
                </span>
            </div>
            
            <!-- Location -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center hover:shadow-md transition-shadow">
                <span class="text-gray-400 text-sm mb-2">الموقع</span>
                <span class="text-xl font-bold text-gray-800">{{ $project->location ?? 'غير محدد' }}</span>
            </div>

             <!-- Date -->
             <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center hover:shadow-md transition-shadow">
                <span class="text-gray-400 text-sm mb-2">تاريخ الإضافة</span>
                <span class="text-xl font-bold text-gray-800">{{ $project->created_at->format('Y-m-d') }}</span>
            </div>
        </div>

        {{-- Properties Section --}}
         <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
             <div class="flex items-center mb-8">
                 <div class="w-1.5 h-8 bg-[#498e49] rounded-full ml-3"></div>
                <h2 class="text-2xl font-bold text-gray-800">الوحدات التابعة للمشروع</h2>
            </div>
            
            @if($project->properties->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                    <svg class="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    <span class="text-lg">لا توجد وحدات مضافة لهذا المشروع حالياً</span>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($project->properties as $property)
                        <a href="{{ route('properties', $property->id) }}" class="block">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-all duration-300 group hover:-translate-y-1">
                            <div class="h-48 w-full overflow-hidden bg-gray-100">
                                @if($property->propertiesImages->count() > 0)
                                     <img src="{{ asset('storage/' . $property->propertiesImages->first()->url) }}" alt="{{ $property->name }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                @else
                                     <div class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-4">
                                     <div>
                                        <h3 class="text-lg font-bold text-gray-800 mb-2">{{ $property->name }}</h3>
                                         <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#498e49]/10 text-[#498e49]">
                                            {{ $property->type }}
                                        </span>
                                     </div>
                                     <div class="text-left">
                                         <span class="block text-xl font-bold text-[#498e49]">{{ number_format($property->price) }}</span>
                                         <span class="text-xs text-gray-400">ريال</span>
                                     </div>
                                </div>

                                <div class="grid grid-cols-3 gap-2 border-t border-gray-50 pt-4 mt-4">
                                    <div class="text-center">
                                        <span class="block text-xs text-gray-400 mb-1">المساحة</span>
                                        <span class="block text-sm font-bold text-gray-700">{{ $property->area }} م²</span>
                                    </div>
                                     <div class="text-center border-r border-gray-100 px-2">
                                        <span class="block text-xs text-gray-400 mb-1">الغرف</span>
                                        <span class="block text-sm font-bold text-gray-700">{{ $property->rooms }}</span>
                                    </div>
                                     <div class="text-center border-r border-gray-100">
                                        <span class="block text-xs text-gray-400 mb-1">دورات المياه</span>
                                        <span class="block text-sm font-bold text-gray-700">{{ $property->bathrooms }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
                </div>
            @endif
        </div>

    </div>
@endsection
