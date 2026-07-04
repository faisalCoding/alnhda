@extends('admin.layouts.panel')

@section('title', 'الزوار والطلبات')
@section('heading', 'الزوار والطلبات')

@section('content')
    <div x-data="visitorsPage()" class="space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <input type="search" x-model="search" placeholder="بحث بالاسم أو الجوال أو البريد…"
                class="w-full max-w-sm rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">

            <button type="button" @click="refresh()"
                class="flex items-center gap-1.5 rounded-xl border border-zinc-300 px-4 py-2 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                تحديث
            </button>
        </div>

        <div x-show="!groups.length" class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد طلبات زوار بعد</p>
            <p class="mt-1 text-sm text-zinc-400">تظهر هنا طلبات التواصل الواردة من نماذج الموقع</p>
        </div>

        <template x-for="group in groups" :key="group.key">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <header class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <h2 class="font-extrabold" x-text="group.label"></h2>
                    <span class="rounded-full bg-primary-500/10 px-3 py-1 text-xs font-bold text-primary-600 dark:text-primary-300"
                        x-text="group.items.length + ' طلب'"></span>
                </header>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-right text-sm">
                        <thead>
                            <tr class="border-b border-zinc-100 text-xs text-zinc-400 dark:border-zinc-800">
                                <th class="px-5 py-3 font-bold">التاريخ</th>
                                <th class="px-5 py-3 font-bold">الاسم</th>
                                <th class="px-5 py-3 font-bold">الجوال</th>
                                <th class="px-5 py-3 font-bold">البريد الإلكتروني</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <template x-for="visitor in group.items" :key="visitor.id">
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="whitespace-nowrap px-5 py-3 text-xs text-zinc-500 dark:text-zinc-400"
                                        x-text="formatDate(visitor.created_at)"></td>
                                    <td class="px-5 py-3 font-medium"
                                        x-text="((visitor.first_name ?? '') + ' ' + (visitor.last_name ?? '')).trim() || '—'"></td>
                                    <td class="px-5 py-3">
                                        <a :href="'tel:' + visitor.phone" dir="ltr"
                                            class="font-medium text-primary-600 hover:underline dark:text-primary-300"
                                            x-text="visitor.phone ?? '—'"></a>
                                    </td>
                                    <td class="px-5 py-3">
                                        <a :href="'mailto:' + visitor.email" dir="ltr"
                                            class="text-zinc-600 hover:underline dark:text-zinc-300"
                                            x-text="visitor.email ?? '—'"></a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
@endsection
