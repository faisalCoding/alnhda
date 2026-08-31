@extends('admin.layouts.panel')

@section('title', 'المقالات')
@section('heading', 'المقالات')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $toolbarButton = 'rounded-lg bg-zinc-100 px-2.5 py-1.5 font-mono text-xs font-bold hover:bg-primary-500/15 hover:text-primary-600 dark:bg-zinc-800 dark:hover:text-primary-300';
@endphp

@section('content')
    <div x-data="articlesPage()" class="space-y-6">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <input type="search" x-model="search" placeholder="بحث بالعنوان…"
                class="w-full max-w-xs rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">
            <button type="button" @click="openCreate()"
                class="flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-primary-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                مقال جديد
            </button>
        </div>

        {{-- Empty state --}}
        <div x-show="!articles.length" class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد مقالات بعد</p>
            <p class="mt-1 text-sm text-zinc-400">اكتب أول مقال — يُحفظ محليًا ويُزامن تلقائيًا</p>
        </div>

        {{-- Articles grid --}}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="article in articles" :key="article.id">
                <article class="flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="relative h-40 bg-zinc-100 dark:bg-zinc-800">
                        <img :src="article.image_full_url ?? '/img/article.jpg'" :alt="article.title"
                            class="h-full w-full object-cover" loading="lazy">
                        <span class="absolute right-3 top-3">
                            @include('admin.partials.sync-badge', ['record' => 'article'])
                        </span>
                    </div>

                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <h3 class="line-clamp-2 font-extrabold leading-snug" x-text="article.title"></h3>
                        <p class="text-xs text-zinc-400"
                            x-text="article.created_at ? new Date(article.created_at).toLocaleDateString('ar-SA') : 'لم يُزامن بعد'"></p>

                        <template x-if="ctaSummary(article)">
                            <span class="inline-flex w-fit items-center gap-1 rounded-lg bg-primary-500/10 px-2 py-1 text-[11px] font-bold text-primary-600 dark:text-primary-300">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757" />
                                </svg>
                                <span x-text="ctaSummary(article)"></span>
                            </span>
                        </template>

                        <div class="mt-auto flex items-center gap-2 pt-2">
                            <button type="button" @click="openEdit(article)"
                                class="flex-1 rounded-xl bg-zinc-100 px-3 py-2 text-xs font-bold hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                                تعديل
                            </button>
                            <button type="button" @click="remove(article)"
                                class="rounded-xl px-3 py-2 text-xs font-bold text-red-500 hover:bg-red-500/10">
                                حذف
                            </button>
                        </div>
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
                    <h2 class="font-extrabold" x-text="editingId ? 'تعديل مقال' : 'مقال جديد'"></h2>
                    <button type="button" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="closePanel()">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                    <div>
                        <label class="{{ $label }}">عنوان المقال</label>
                        <input type="text" x-model="form.title" class="{{ $input }}" placeholder="عنوان جذاب للمقال…">
                        <p class="{{ $error }}" x-show="errors.title" x-text="errors.title"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">المحتوى (يدعم HTML)</label>

                        <div class="mb-2 flex flex-wrap gap-1.5" dir="ltr">
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<p>', '</p>')">p</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<h1>', '</h1>')">h1</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<h2>', '</h2>')">h2</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<h3>', '</h3>')">h3</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<b>', '</b>')">b</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<a href=&quot;&quot;>', '</a>')">a</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<span>', '</span>')">span</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<div>', '</div>')">div</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<br>', '')">br</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<img src=&quot;&quot; alt=&quot;&quot;>', '')">img</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<ul>\n', '\n</ul>')">ul</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<ol>\n', '\n</ol>')">ol</button>
                            <button type="button" class="{{ $toolbarButton }}" @click="insertTag('<li>', '</li>')">li</button>
                        </div>

                        <textarea x-ref="contentArea" x-model="form.content" rows="14"
                            class="{{ $input }} font-mono leading-relaxed" placeholder="اكتب محتوى المقال هنا…"></textarea>
                    </div>

                    {{-- Article button --}}
                    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <h3 class="mb-1 text-sm font-extrabold">زر في نهاية المقال</h3>
                        <p class="mb-3 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            اختياري — يفتح مشروعًا أو وحدة أو مقالًا آخر. اتركه «بدون زر» ولن يظهر شيء للقارئ.
                        </p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="{{ $label }}">الوجهة</label>
                                <select x-model="form.cta_target" class="{{ $input }}">
                                    <option value="">بدون زر</option>
                                    <template x-for="group in ctaOptions" :key="group.type">
                                        <optgroup :label="group.label">
                                            <template x-for="option in group.options" :key="option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                        </optgroup>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-zinc-400">
                                    لا تظهر هنا السجلات التي لم تُزامن بعد — زامنها أولًا ثم اربطها.
                                </p>
                            </div>

                            <div>
                                <label class="{{ $label }}">نص الزر</label>
                                <input type="text" x-model="form.cta_label" :disabled="!form.cta_target"
                                    class="{{ $input }} disabled:opacity-50" placeholder="تصفّح المشروع…">
                                <p class="{{ $error }}" x-show="errors.cta_label" x-text="errors.cta_label"></p>
                                <p class="mt-1 text-xs text-zinc-400">يُستخدم اسم الوجهة إذا تُرك فارغًا.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Cover image --}}
                    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <h3 class="mb-3 text-sm font-extrabold">صورة الغلاف</h3>

                        <template x-if="!editingId">
                            <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                يُنشأ المقال بصورة الغلاف الافتراضية — احفظه ثم افتحه للتعديل لرفع صورة مخصصة.
                            </p>
                        </template>

                        <template x-if="editingId && isTemp(editingId)">
                            <p class="text-xs leading-relaxed text-amber-600 dark:text-amber-400">
                                هذا المقال بانتظار المزامنة — احفظ وزامن أولًا ثم ارفع صورة الغلاف.
                            </p>
                        </template>

                        <template x-if="editingId && !isTemp(editingId)">
                            <div class="space-y-3">
                                <p x-show="!$store.sync.online" class="text-xs font-bold text-amber-600 dark:text-amber-400">
                                    رفع الملفات يتطلب اتصالًا بالإنترنت.
                                </p>

                                <img :src="editingRecord?.image_full_url ?? '/img/article.jpg'"
                                    class="h-24 w-40 rounded-lg object-cover" alt="">

                                <label class="block">
                                    <span class="mb-1.5 block text-xs font-bold">صورة جديدة (تُستبدل الحالية)</span>
                                    <input type="file" accept="image/*" :disabled="!$store.sync.online"
                                        @change="uploadCover($event, editingRecord)"
                                        class="block w-full text-xs file:ml-3 file:rounded-lg file:border-0 file:bg-primary-500/10 file:px-3 file:py-2 file:text-xs file:font-bold file:text-primary-600 disabled:opacity-50 dark:file:text-primary-300">
                                </label>
                            </div>
                        </template>
                    </div>
                </div>

                <footer class="flex items-center gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <button type="button" @click="save()"
                        class="flex-1 rounded-xl bg-primary-500 px-4 py-3 text-sm font-extrabold text-white hover:bg-primary-600">
                        <span x-text="editingId ? 'حفظ التعديلات' : 'إنشاء المقال'"></span>
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
