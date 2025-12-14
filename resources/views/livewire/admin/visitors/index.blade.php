<div class="p-6">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">طلبات الزوار</h1>
        <p class="text-gray-500 text-sm mt-1">عرض جميع الطلبات والاستفسارات مصنفة حسب المصدر</p>
    </div>

    <div class="space-y-8">
        @forelse ($visitorsGroups as $formName => $visitors)
            <div
                class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div
                    class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-lg text-gray-800 dark:text-white capitalize">
                            @if ($formName == 'header_villa')
                                طلبات الفلل (هيدر)
                            @elseif($formName == 'header_apartment')
                                طلبات الشقق (هيدر)
                            @elseif($formName == 'header_floor')
                                طلبات الأدوار (هيدر)
                            @elseif($formName == 'newsletter')
                                القائمة البريدية
                            @else
                                {{ $formName }}
                            @endif
                        </h2>
                        <span
                            class="text-xs text-gray-500 font-medium bg-white dark:bg-zinc-800 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-600 mt-1 inline-block">
                            {{ count($visitors) }} طلب
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-xs uppercase text-gray-500 font-medium">
                            <tr>
                                <th class="px-6 py-3">التاريخ</th>
                                <th class="px-6 py-3">الاسم</th>
                                <th class="px-6 py-3">رقم الجوال</th>
                                <th class="px-6 py-3">البريد الإلكتروني</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($visitors as $visitor)
                                <tr
                                    class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors text-sm text-gray-700 dark:text-gray-300">
                                    <td class="px-6 py-4 whitespace-nowrap" dir="ltr">
                                        {{ $visitor->created_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                        {{ $visitor->first_name }} {{ $visitor->last_name }}
                                        @if (!$visitor->first_name && !$visitor->last_name)
                                            <span class="text-gray-400 italic">بدون اسم</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4" dir="ltr text-right">
                                        <a href="tel:{{ $visitor->phone }}"
                                            class="hover:text-[#49A035] transition-colors font-mono">
                                            {{ $visitor->phone ?? '-' }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 font-mono">
                                        <a href="mailto:{{ $visitor->email }}"
                                            class="hover:text-[#49A035] transition-colors">
                                            {{ $visitor->email ?? '-' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div
                class="text-center py-12 bg-white dark:bg-zinc-900 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                <svg class="w-12 h-12 text-zinc-400 mx-auto mb-4" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                    </path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">لا توجد طلبات حتى الآن</h3>
                <p class="text-gray-500 text-sm mt-1">لم يتم تسجيل أي زيارات أو طلبات بعد.</p>
            </div>
        @endforelse
    </div>
</div>
