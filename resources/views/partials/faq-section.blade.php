@php
    $faq = app(\App\Services\HomeFacts::class)->faq();
@endphp

@if ($faq)
    <section class="bg-white w-full py-16 md:py-24" dir="rtl" aria-labelledby="faq-heading">
        <div class="container mx-auto px-4 md:px-6 max-w-4xl">

            <div class="mb-10 text-center">
                <span class="inline-block px-4 py-1 rounded-full border border-[#498E49] text-[#498E49] text-sm font-medium">
                    أسئلة متكررة
                </span>
                <h2 id="faq-heading" class="mt-4 text-3xl md:text-4xl font-bold text-gray-900 leading-tight">
                    أسئلة يسألها عملاؤنا قبل الشراء
                </h2>
            </div>

            {{-- <details> rather than a scripted accordion: the answers are in the
                 markup either way, and this one still opens with JavaScript off. --}}
            <div class="flex flex-col gap-4">
                @foreach ($faq as $entry)
                    <details class="group rounded-2xl border border-gray-200 bg-[#fcfdfc] px-6 py-5 open:shadow-sm">
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-4 text-lg font-bold text-gray-900 marker:hidden">
                            <h3 class="text-lg font-bold">{{ $entry['question'] }}</h3>
                            <span class="shrink-0 text-[#498E49] transition-transform duration-300 group-open:rotate-180">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </span>
                        </summary>
                        <p class="mt-4 text-gray-600 text-base leading-loose">{{ $entry['answer'] }}</p>
                    </details>
                @endforeach
            </div>

            <p class="mt-8 text-center text-gray-500">
                لم تجد إجابتك؟
                <a href="{{ route('contact-us') }}" class="font-bold text-[#498E49] hover:underline">
                    تواصل مع كيان النهضة العقارية مباشرة
                </a>
            </p>

        </div>
    </section>
@endif
