@php
    $districts = app(\App\Services\HomeFacts::class)->projectsByDistrict();
@endphp

@if ($districts)
    <section class="bg-[#f5fdf5] w-full py-16 md:py-24" dir="rtl" aria-labelledby="districts-heading">
        <div class="container mx-auto px-4 md:px-6">

            <div class="mb-12 text-center">
                <span class="inline-block px-4 py-1 rounded-full border border-[#498E49] text-[#498E49] text-sm font-medium">
                    مواقعنا
                </span>
                <h2 id="districts-heading" class="mt-4 text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                    أين نبني في جدة
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-gray-600 text-lg leading-relaxed">
                    تختار كيان النهضة العقارية مواقع مشاريعها في أحياء جدة القريبة من الخدمات والطرق الرئيسية.
                    هذه الأحياء التي نبني فيها اليوم، ومشروع كل حي بتفاصيله ومخططاته وأسعاره في صفحته.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($districts as $district => $projects)
                    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="shrink-0 rounded-full bg-[#498E49]/10 p-2.5 text-[#498E49]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                            </span>
                            <h3 class="text-xl font-bold text-gray-900">{{ $district }}</h3>
                        </div>

                        <ul class="flex flex-col gap-2">
                            @foreach ($projects as $project)
                                <li>
                                    <a href="{{ route('project', $project) }}"
                                        class="group flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-gray-700 transition-colors hover:bg-[#498E49]/5 hover:text-[#498E49]">
                                        <span class="font-medium">{{ $project->name }}</span>
                                        <span class="text-xs text-gray-400 group-hover:text-[#498E49]">{{ $project->project_type }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

        </div>
    </section>
@endif
