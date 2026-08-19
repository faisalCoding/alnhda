@extends('admin.layouts.panel')

@section('title', 'الحسابات')
@section('heading', 'الحسابات')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $ghost = 'inline-flex items-center gap-1.5 rounded-xl border border-zinc-300 px-3.5 py-2 text-sm font-bold text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $primary = 'inline-flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-primary-600 disabled:opacity-50';
    $danger = 'inline-flex items-center gap-1.5 rounded-xl border border-red-200 px-3.5 py-2 text-sm font-bold text-red-500 transition hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-900/30';
@endphp

@section('content')
    <div x-data="accountsPage()" class="space-y-5">

        {{-- Summary --}}
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">الحسابات المعروضة</p>
                <p class="mt-1 text-2xl font-extrabold" x-text="visible.length"></p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">التصنيفات</p>
                <p class="mt-1 text-2xl font-extrabold" x-text="categories.length"></p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">اكتمال المهام</p>
                <div class="mt-2 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-primary-500 transition-all duration-500"
                            :style="`width: ${overallProgress.percent}%`"></div>
                    </div>
                    <span class="text-sm font-extrabold" x-text="`${overallProgress.done}/${overallProgress.total}`"></span>
                </div>
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" @click="openCreate()" class="{{ $primary }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                حساب جديد
            </button>

            <div class="relative">
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400"
                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="search" x-model="search" placeholder="بحث بالاسم أو المعرّف…"
                    class="w-64 rounded-xl border border-zinc-300 bg-white py-2 pr-9 pl-3.5 text-sm outline-none transition focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">
            </div>

            <button type="button" @click="showCategories = true" class="{{ $ghost }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                </svg>
                التصنيفات
            </button>

            <button type="button" @click="showTemplates = true" class="{{ $ghost }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                </svg>
                المهام النموذجية
                <span class="rounded-full bg-zinc-200 px-1.5 text-xs dark:bg-zinc-700" x-text="templates.length"></span>
            </button>

            <button type="button" x-show="!pinIsSet" x-cloak @click="pinSetup.open = true"
                class="mr-auto inline-flex items-center gap-1.5 rounded-xl bg-amber-100 px-3.5 py-2 text-xs font-bold text-amber-800 transition hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-200">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                عيّن رمز الإظهار
            </button>
        </div>

        {{-- Category filter --}}
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="activeCategory = 'all'"
                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition"
                :class="activeCategory === 'all' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300'">
                الكل <span x-text="countFor('all')"></span>
            </button>

            <template x-for="category in categories" :key="category.id">
                <button type="button" @click="activeCategory = category.id"
                    class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition"
                    :class="activeCategory === category.id ? classesFor(category.color).chip + ' ring-2 ' + classesFor(category.color).ring : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300'">
                    <span class="h-2 w-2 rounded-full" :class="classesFor(category.color).dot"></span>
                    <span x-text="category.name"></span>
                    <span x-text="countFor(category.id)"></span>
                </button>
            </template>

            <button type="button" x-show="countFor('none')" x-cloak @click="activeCategory = 'none'"
                class="rounded-full px-3.5 py-1.5 text-xs font-bold transition"
                :class="activeCategory === 'none' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300'">
                بلا تصنيف <span x-text="countFor('none')"></span>
            </button>
        </div>

        <p x-show="error" x-cloak
            class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600 dark:bg-red-900/30 dark:text-red-300"
            x-text="error"></p>

        {{-- Loading skeleton --}}
        <div x-show="loading" class="grid gap-4 lg:grid-cols-2">
            <template x-for="n in 2" :key="n">
                <div class="animate-pulse space-y-3 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="h-4 w-1/3 rounded bg-zinc-200 dark:bg-zinc-800"></div>
                    <div class="h-3 w-1/2 rounded bg-zinc-100 dark:bg-zinc-800/60"></div>
                    <div class="h-2 w-full rounded bg-zinc-100 dark:bg-zinc-800/60"></div>
                </div>
            </template>
        </div>

        {{-- Empty --}}
        <div x-show="!loading && !visible.length" x-cloak
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400"
                x-text="accounts.length ? 'لا نتائج مطابقة' : 'لا توجد حسابات بعد'"></p>
            <p class="mt-1 text-sm text-zinc-400"
                x-text="accounts.length ? 'جرّب تغيير البحث أو التصنيف' : 'أضف حساباً لتبدأ متابعة مهامه'"></p>
        </div>

        {{-- Accounts --}}
        <div x-show="visible.length" x-cloak class="sheet-wrap">
            <table class="sheet">
                <thead>
                    <tr>
                        <th class="gutter">#</th>
                        <th>المنصة</th>
                        <th>التصنيفات</th>
                        <th>اسم المستخدم</th>
                        <th>كلمة المرور</th>
                        <th>المهام</th>
                        <th></th>
                    </tr>
                </thead>

                {{-- One tbody per account: x-for needs a single root, and two rows
                     per record cannot share one. Several tbody in a table is valid. --}}
                <template x-for="(account, index) in visible" :key="account.id">
                    <tbody>
                        <tr>
                            <td class="gutter" x-text="index + 1"></td>
                            <td class="font-bold">
                                <span class="flex items-center gap-1.5">
                                    <span x-text="account.name"></span>
                                    <template x-if="account.url">
                                        <a :href="account.url" target="_blank" rel="noopener noreferrer"
                                            class="text-zinc-400 transition hover:text-primary-600 dark:hover:text-primary-300"
                                            :title="'فتح ' + account.name">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                            </svg>
                                        </a>
                                    </template>
                                </span>
                            </td>
                            <td>
                                <span class="flex flex-wrap gap-1">
                                    <template x-for="category in account.categories" :key="category.id">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold"
                                            :class="classesFor(category.color).chip">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="classesFor(category.color).dot"></span>
                                            <span x-text="category.name"></span>
                                        </span>
                                    </template>
                                    <span x-show="!account.categories.length" class="text-zinc-400">—</span>
                                </span>
                            </td>
                            <td>
                                <button type="button" @click="copyIdentifier(account)"
                                    class="group/copy flex items-center gap-1.5 text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white"
                                    :title="'نسخ ' + account.identifier">
                                    <span dir="ltr" x-text="account.identifier"></span>
                                    <span x-show="copied === 'id-' + account.id" x-cloak
                                        class="text-xs font-bold text-emerald-600 dark:text-emerald-400">تم النسخ</span>
                                </button>
                            </td>
                            <td>
                                <template x-if="account.has_password && !revealed[account.id]">
                                    <button type="button" @click="askForPin(account)"
                                        class="font-mono tracking-widest text-zinc-400 transition hover:text-primary-600 dark:hover:text-primary-300"
                                        title="إظهار كلمة المرور">••••••</button>
                                </template>
                                <template x-if="revealed[account.id]">
                                    <span class="flex items-center gap-2">
                                        <code class="text-xs font-bold" dir="ltr" x-text="revealed[account.id]"></code>
                                        <button type="button" @click="copyPassword(account.id)"
                                            class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300"
                                            x-text="copied === 'pw-' + account.id ? 'تم' : 'نسخ'"></button>
                                        <button type="button" @click="hide(account.id)"
                                            class="text-xs font-bold text-zinc-500 hover:underline">إخفاء</button>
                                    </span>
                                </template>
                                <span x-show="!account.has_password" class="text-zinc-400">—</span>
                            </td>
                            <td>
                                <button type="button" @click="toggleTasks(account.id)"
                                    class="flex items-center gap-2 transition hover:text-primary-600 dark:hover:text-primary-300">
                                    <span class="h-1.5 w-16 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                        <span class="block h-full rounded-full transition-all"
                                            :class="progress(account).percent === 100 ? 'bg-emerald-500' : 'bg-primary-500'"
                                            :style="`width: ${progress(account).percent}%`"></span>
                                    </span>
                                    <span class="text-xs font-bold" x-text="`${progress(account).done}/${progress(account).total}`"></span>
                                    <svg class="h-3.5 w-3.5 transition-transform" :class="expanded[account.id] && 'rotate-180'"
                                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <button type="button" @click="openEdit(account)"
                                        class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">تعديل</button>
                                    <button type="button" @click="deleteAccount(account)"
                                        class="text-xs font-bold text-red-500 hover:underline">حذف</button>
                                </div>
                            </td>
                        </tr>

                        {{-- The task list opens as a full-width row beneath its account. --}}
                        <tr x-show="expanded[account.id]" x-cloak>
                            <td class="gutter"></td>
                            <td colspan="6" class="whitespace-normal bg-zinc-50 dark:bg-zinc-800/40">
                                <ul class="space-y-0.5 py-1">
                                    <template x-for="task in account.tasks" :key="task.id">
                                        <li class="group/task flex items-center gap-2">
                                            <input type="checkbox" :checked="task.is_done" @change="toggleTask(account, task)"
                                                class="h-3.5 w-3.5 shrink-0 rounded border-zinc-300 text-primary-500 focus:ring-primary-500 dark:border-zinc-600">
                                            <span class="flex-1" :class="task.is_done && 'text-zinc-400 line-through'" x-text="task.title"></span>
                                            <button type="button" @click="deleteTask(account, task)"
                                                class="text-xs font-bold text-red-500 opacity-0 transition group-hover/task:opacity-100">حذف</button>
                                        </li>
                                    </template>
                                    <li x-show="!account.tasks.length" x-cloak class="text-zinc-400">لا توجد مهام</li>
                                </ul>

                                <div class="flex flex-wrap gap-2 pb-1">
                                    <form @submit.prevent="addTask(account)" class="flex flex-1 gap-2">
                                        <input type="text" x-model="newTask[account.id]" placeholder="مهمة خاصة بهذا الحساب…"
                                            class="w-full rounded-lg border border-zinc-300 bg-white px-2.5 py-1 text-xs outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">
                                        <button type="submit" class="rounded-lg border border-zinc-300 px-3 py-1 text-xs font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">إضافة</button>
                                    </form>
                                    <button type="button" @click="applyTemplates(account)"
                                        class="rounded-lg border border-zinc-300 px-3 py-1 text-xs font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">استيراد النموذجية</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </template>
            </table>
        </div>

        @include('admin.partials.accounts-modals', ['input' => $input, 'label' => $label, 'error' => $error, 'ghost' => $ghost, 'primary' => $primary, 'danger' => $danger])
    </div>
@endsection
