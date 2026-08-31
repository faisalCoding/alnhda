@extends('admin.layouts.panel')

@section('title', 'الصفحات المجمّعة')
@section('heading', 'الصفحات المجمّعة')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
@endphp

@section('content')
    <div x-data="collectionsPage()" class="space-y-6">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <input type="search" x-model="search" placeholder="بحث بالعنوان أو الرابط…"
                class="w-full max-w-xs rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">
            <button type="button" @click="openCreate()"
                class="flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-primary-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                صفحة جديدة
            </button>
        </div>

        {{-- Empty state --}}
        <div x-show="!collections.length" class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد صفحات مجمّعة بعد</p>
            <p class="mt-1 text-sm text-zinc-400">اجمع مشاريع ووحدات ومقالات في صفحة واحدة، وشارك رابطها في حملتك</p>
        </div>

        {{-- Pages --}}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="page in collections" :key="page.id">
                <article class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-extrabold leading-snug" x-text="page.title"></h3>
                        @include('admin.partials.sync-badge', ['record' => 'page'])
                    </div>

                    <p class="truncate font-mono text-xs text-zinc-400" x-text="'/collections/' + (page.slug ?? '')"></p>

                    <p class="line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400" x-text="page.description"></p>

                    <span class="w-fit rounded-lg bg-primary-500/10 px-2 py-1 text-[11px] font-bold text-primary-600 dark:text-primary-300"
                        x-text="itemCount(page) + ' عنصر'"></span>

                    <div class="mt-auto flex items-center gap-2 pt-2">
                        <button type="button" @click="openEdit(page)"
                            class="flex-1 rounded-xl bg-zinc-100 px-3 py-2 text-xs font-bold hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                            تعديل
                        </button>
                        <a x-show="!isTemp(page.id)" :href="page.url" target="_blank" rel="noopener"
                            class="rounded-xl bg-zinc-100 px-3 py-2 text-xs font-bold hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                            معاينة
                        </a>
                        <button type="button" @click="remove(page)"
                            class="rounded-xl px-3 py-2 text-xs font-bold text-red-500 hover:bg-red-500/10">
                            حذف
                        </button>
                    </div>
                </article>
            </template>
        </div>

        {{-- Slide-over panel --}}
        <div x-show="panel" x-cloak class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closePanel()" x-show="panel" x-transition.opacity></div>

            <div class="panel-slide-enter absolute inset-y-0 left-0 flex w-full max-w-3xl flex-col bg-white shadow-2xl dark:bg-zinc-900"
                x-show="panel" x-trap.noscroll="panel" @keydown.escape.window="closePanel()">

                <header class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-extrabold" x-text="editingId ? 'تعديل صفحة' : 'صفحة جديدة'"></h2>
                    <button type="button" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="closePanel()">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                    <div>
                        <label class="{{ $label }}">عنوان الصفحة</label>
                        <input type="text" x-model="form.title" @input="suggestSlug()" class="{{ $input }}"
                            placeholder="شقق جاهزة للتسليم">
                        <p class="{{ $error }}" x-show="errors.title" x-text="errors.title"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">رابط الصفحة</label>
                        <div class="flex items-center gap-2">
                            <span class="shrink-0 font-mono text-xs text-zinc-400">/collections/</span>
                            <input type="text" x-model="form.slug" @input="slugTouched = true"
                                class="{{ $input }} font-mono" placeholder="شقق-جاهزة">
                        </div>
                        <p class="{{ $error }}" x-show="errors.slug" x-text="errors.slug"></p>
                        <p class="mt-1 text-xs text-zinc-400">هذا ما يُنسخ في الحملات — غيّره بعد النشر وتنكسر الروابط المنشورة.</p>
                    </div>

                    <div>
                        <label class="{{ $label }}">الوصف</label>
                        <textarea x-model="form.description" rows="3" class="{{ $input }} leading-relaxed"
                            placeholder="سطران يشرحان ما الذي يجمع هذه الصفحة…"></textarea>
                        <p class="mt-1 text-xs text-zinc-400">يظهر أعلى الصفحة، ويُستخدم وصفًا لها في نتائج البحث.</p>
                    </div>

                    {{-- Arrangement --}}
                    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <h3 class="mb-1 text-sm font-extrabold">محتوى الصفحة</h3>
                        <p class="mb-3 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            أضف مشاريع ووحدات ومقالات، ورتّبها بالأسهم — الترتيب هنا هو الترتيب الذي يراه الزائر.
                        </p>

                        <div class="mb-4 flex flex-wrap items-center gap-2">
                            <select x-model="picked" class="{{ $input }} flex-1">
                                <option value="">اختر ما تضيفه…</option>
                                <template x-for="group in itemOptions" :key="group.type">
                                    <optgroup :label="group.label">
                                        <template x-for="option in group.options" :key="option.value">
                                            <option :value="option.value" x-text="option.label"></option>
                                        </template>
                                    </optgroup>
                                </template>
                            </select>
                            <button type="button" @click="addPicked()" :disabled="!picked"
                                class="rounded-xl bg-primary-500 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-primary-600 disabled:opacity-40">
                                إضافة
                            </button>
                        </div>

                        <p x-show="!form.items.length" class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-xs text-zinc-400 dark:border-zinc-700">
                            الصفحة فارغة — أضف أول عنصر من القائمة أعلاه.
                        </p>

                        <ul class="flex flex-col gap-2">
                            <template x-for="(item, index) in form.items" :key="item">
                                <li class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                                    <span class="w-6 shrink-0 text-center text-xs font-bold text-zinc-400" x-text="index + 1"></span>

                                    <span class="shrink-0 rounded-lg bg-primary-500/10 px-2 py-0.5 text-[11px] font-bold text-primary-600 dark:text-primary-300"
                                        x-text="itemKind(item)"></span>

                                    <span class="flex-1 truncate text-sm font-bold" x-text="itemLabel(item)"></span>

                                    <button type="button" @click="move(index, -1)" :disabled="index === 0"
                                        class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-200 disabled:opacity-30 dark:hover:bg-zinc-700"
                                        aria-label="تحريك لأعلى">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                        </svg>
                                    </button>
                                    <button type="button" @click="move(index, 1)" :disabled="index === form.items.length - 1"
                                        class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-200 disabled:opacity-30 dark:hover:bg-zinc-700"
                                        aria-label="تحريك لأسفل">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <button type="button" @click="removeItem(index)"
                                        class="rounded-lg p-1.5 text-red-500 hover:bg-red-500/10" aria-label="إزالة">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <footer class="flex items-center gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <button type="button" @click="save()"
                        class="flex-1 rounded-xl bg-primary-500 px-4 py-3 text-sm font-extrabold text-white hover:bg-primary-600">
                        <span x-text="editingId ? 'حفظ التعديلات' : 'إنشاء الصفحة'"></span>
                        <span class="text-xs font-normal opacity-80" x-show="!$store.sync.online">(سيُزامن عند عودة الاتصال)</span>
                    </button>
                    <button type="button" @click="closePanel()"
                        class="rounded-xl border border-zinc-300 px-4 py-3 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        إلغاء
                    </button>
                </footer>
            </div>
        </div>
    </div>
@endsection
