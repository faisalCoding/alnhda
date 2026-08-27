@extends('admin.layouts.panel')

@section('title', 'المهام الأسبوعية')
@section('heading', 'المهام الأسبوعية')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $ghost = 'inline-flex items-center gap-1.5 rounded-xl border border-zinc-300 px-3.5 py-2 text-sm font-bold text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $primary = 'inline-flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-primary-600 disabled:opacity-50';
@endphp

@section('content')
    <div x-data="weeklyTasksPage()" class="space-y-5">

        {{-- Summary --}}
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">الموظفون</p>
                <p class="mt-1 text-2xl font-extrabold" x-text="employees.filter(e => e.is_active).length"></p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">قوائم هذا الأسبوع</p>
                <p class="mt-1 text-2xl font-extrabold" x-text="lists.length"></p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">الإنجاز</p>
                <div class="mt-2 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                        <div class="h-full rounded-full transition-all duration-500"
                            :class="overall.percent === 100 ? 'bg-emerald-500' : 'bg-primary-500'"
                            :style="`width: ${overall.percent}%`"></div>
                    </div>
                    <span class="text-sm font-extrabold" x-text="`${overall.done}/${overall.total}`"></span>
                </div>
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" @click="generate()" :disabled="busy" class="{{ $primary }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                توليد قوائم الأسبوع
            </button>

            <button type="button" @click="carryForward()" :disabled="busy" class="{{ $ghost }}"
                title="ينقل ما لم يُنجَز في الأسبوع الماضي إلى قوائم هذا الأسبوع، دون المساس بسجلّ الأسبوع الماضي">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                </svg>
                ترحيل متأخّرات الأسبوع الماضي
            </button>

            <button type="button" @click="showEmployees = true" class="{{ $ghost }}">
                الموظفون
                <span class="rounded-full bg-zinc-200 px-1.5 text-xs dark:bg-zinc-700" x-text="employees.length"></span>
            </button>

            <button type="button" @click="showTemplates = true" class="{{ $ghost }}">
                المهام النموذجية
                <span class="rounded-full bg-zinc-200 px-1.5 text-xs dark:bg-zinc-700" x-text="templates.length"></span>
            </button>

            <button type="button" @click="showCategories = true" class="{{ $ghost }}">
                التصنيفات
                <span class="rounded-full bg-zinc-200 px-1.5 text-xs dark:bg-zinc-700" x-text="categories.length"></span>
            </button>

            <button type="button" @click="showSettings = true" class="{{ $ghost }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
                مجموعة التقارير
            </button>

            <div class="mr-auto flex items-center gap-2">
                <button type="button" @click="showPreview('opening')" class="{{ $ghost }}">معاينة رسالة السبت</button>
                <button type="button" @click="showPreview('closing')" class="{{ $ghost }}">معاينة رسالة الخميس</button>
            </div>
        </div>

        <p x-show="!settings.is_ready" x-cloak
            class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
            التقارير التلقائية متوقفة. اختر مجموعة واتساب وفعّلها من زر «مجموعة التقارير» لتُرسل كل سبت وخميس.
        </p>

        <p x-show="notice" x-cloak
            class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200"
            x-text="notice"></p>
        <p x-show="error" x-cloak
            class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600 dark:bg-red-900/30 dark:text-red-300"
            x-text="error"></p>

        {{-- Empty --}}
        <div x-show="!loading && !lists.length" x-cloak
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد قوائم لهذا الأسبوع</p>
            <p class="mt-1 text-sm text-zinc-400">أضف موظفين ومهاماً نموذجية ثم اضغط «توليد قوائم الأسبوع»</p>
        </div>

        {{-- Lists --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <template x-for="list in lists" :key="list.id">
                <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <header class="flex items-center gap-3 border-b border-zinc-100 p-5 dark:border-zinc-800">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-500/15 text-sm font-bold text-primary-600 dark:text-primary-300"
                            x-text="list.employee?.name?.slice(0, 1) ?? '؟'"></span>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate font-extrabold" x-text="list.employee?.name"></h2>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400"
                                x-text="[list.employee?.role, 'أسبوع ' + list.week_start].filter(Boolean).join(' · ')"></p>
                        </div>
                        <span class="shrink-0 text-xs font-bold text-zinc-500 dark:text-zinc-400"
                            x-text="`${progress(list).done} / ${progress(list).total}`"></span>
                    </header>

                    <div class="px-5 pt-4">
                        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                            <div class="h-full rounded-full transition-all duration-500"
                                :class="progress(list).percent === 100 ? 'bg-emerald-500' : 'bg-primary-500'"
                                :style="`width: ${progress(list).percent}%`"></div>
                        </div>
                    </div>

                    <div class="px-5 py-3">
                        <template x-for="group in groupsFor(list)" :key="group.key">
                            <section class="mb-1 last:mb-0">
                                <p x-show="group.name" class="mb-1 flex items-center gap-1.5 px-2 pt-1.5">
                                    <span class="h-2 w-2 shrink-0 rounded-full" :class="classesFor(group.color).dot"></span>
                                    <span class="text-xs font-extrabold text-zinc-500 dark:text-zinc-400" x-text="group.name"></span>
                                    <span class="text-[11px] text-zinc-400"
                                        x-text="'· ' + group.items.filter(i => i.is_done).length + '/' + group.items.length"></span>
                                </p>

                                <ul class="space-y-0.5">
                                    <template x-for="item in group.items" :key="item.id">
                                        <li class="group flex items-center gap-3 rounded-lg px-2 py-1.5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                            <input type="checkbox" :checked="item.is_done" @change="toggleItem(list, item)"
                                                class="h-4 w-4 shrink-0 rounded border-zinc-300 text-primary-500 focus:ring-primary-500 dark:border-zinc-600">
                                            <span class="flex-1 text-sm" :class="item.is_done && 'text-zinc-400 line-through'" x-text="item.title"></span>

                                            {{-- مهمة مرحّلة: الأسبوع الذي استُحقّت فيه أولاً، لا الذي سبق هذا. --}}
                                            <span x-show="item.carried_from" x-cloak
                                                :title="'لم تُنجَز في أسبوع ' + item.carried_from"
                                                class="shrink-0 rounded-lg bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">مُرحَّلة</span>

                                            <select x-show="categories.length" :value="item.weekly_task_category_id ?? ''"
                                                @change="moveItem(list, item, $event.target.value)"
                                                title="نقل المهمة إلى تصنيف آخر"
                                                class="shrink-0 rounded-lg border-0 bg-transparent py-0 text-[11px] text-zinc-400 opacity-0 transition focus:opacity-100 group-hover:opacity-100 dark:text-zinc-500">
                                                <option value="">بلا تصنيف</option>
                                                <template x-for="category in categories" :key="category.id">
                                                    <option :value="category.id" x-text="category.name"></option>
                                                </template>
                                            </select>

                                            <button type="button" @click="removeItem(list, item)"
                                                class="shrink-0 text-xs font-bold text-red-500 opacity-0 transition group-hover:opacity-100">حذف</button>
                                        </li>
                                    </template>
                                </ul>
                            </section>
                        </template>

                        <p x-show="!list.items.length" x-cloak class="px-2 py-3 text-sm text-zinc-400">لا توجد مهام</p>
                    </div>

                    <footer class="border-t border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <form @submit.prevent="addItem(list)" class="flex gap-2">
                            <input type="text" x-model="newItem[list.id]" placeholder="مهمة إضافية لهذا الأسبوع…" class="{{ $input }}">
                            <select x-show="categories.length" x-model.number="newItemCategory[list.id]"
                                class="w-32 shrink-0 rounded-xl border border-zinc-300 bg-white px-2 py-2.5 text-xs outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">
                                <option value="">بلا تصنيف</option>
                                <template x-for="category in categories" :key="category.id">
                                    <option :value="category.id" x-text="category.name"></option>
                                </template>
                            </select>
                            <button type="submit" class="{{ $ghost }}">إضافة</button>
                        </form>
                    </footer>
                </section>
            </template>
        </div>

        @include('admin.partials.weekly-tasks-modals', ['input' => $input, 'label' => $label, 'error' => $error, 'ghost' => $ghost, 'primary' => $primary])
    </div>
@endsection
