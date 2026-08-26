@extends('admin.layouts.panel')

@section('title', 'الظهور في البحث والمشاركة')
@section('heading', 'الظهور في البحث والمشاركة')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $ghost = 'inline-flex items-center gap-1.5 rounded-xl border border-zinc-300 px-3.5 py-2 text-sm font-bold text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $primary = 'inline-flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-primary-600 disabled:opacity-50';
    $card = 'rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900';
    $tab = 'rounded-xl px-4 py-2 text-sm font-bold transition';
@endphp

@section('content')
    <div x-data="seoPage()" class="space-y-5">

        {{-- التبويبات --}}
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="tab = 'pages'"
                :class="tab === 'pages' ? 'bg-primary-500 text-white' : 'border border-zinc-300 text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                class="{{ $tab }}">صفحات الموقع</button>

            <button type="button" @click="openRecords()"
                :class="tab === 'records' ? 'bg-primary-500 text-white' : 'border border-zinc-300 text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                class="{{ $tab }}">المشاريع والمقالات والوحدات</button>

            <button type="button" @click="selectDefaults()"
                :class="tab === 'defaults' ? 'bg-primary-500 text-white' : 'border border-zinc-300 text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                class="{{ $tab }}">الافتراضات العامة</button>
        </div>

        <p x-show="error" x-cloak
            class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600 dark:bg-red-900/30 dark:text-red-300"
            x-text="error"></p>

        <div x-show="loading" class="py-16 text-center text-sm text-zinc-400">جارٍ التحميل…</div>

        <div x-show="!loading" x-cloak class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">

            {{-- ── العمود الأيمن: الاختيار والتحرير ── --}}
            <div class="space-y-5">

                {{-- قائمة الصفحات --}}
                <div x-show="tab === 'pages'" class="{{ $card }}">
                    <p class="mb-3 text-sm font-bold">اختر صفحة</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <template x-for="page in pages" :key="page.route_name">
                            <button type="button" @click="selectPage(page)"
                                :class="target?.key === page.route_name ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50'"
                                class="flex items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-right text-sm transition">
                                <span class="font-bold" x-text="page.label"></span>
                                <span x-show="page.title || page.description || page.image_path" x-cloak
                                    class="rounded-lg bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">مخصّصة</span>
                                <span x-show="page.noindex" x-cloak
                                    class="rounded-lg bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">محجوبة</span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- قائمة السجلات --}}
                <div x-show="tab === 'records'" class="{{ $card }}">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <template x-for="type in recordTypes" :key="type.key">
                            <button type="button" @click="recordType = type.key; loadRecords()"
                                :class="recordType === type.key ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'border border-zinc-300 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300'"
                                class="rounded-lg px-3 py-1.5 text-xs font-bold transition" x-text="type.label"></button>
                        </template>

                        <input type="search" x-model="recordSearch" @input.debounce.300ms="loadRecords()"
                            placeholder="بحث بالاسم…"
                            class="ms-auto w-48 rounded-xl border border-zinc-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">
                    </div>

                    <p x-show="recordsLoading" class="py-6 text-center text-sm text-zinc-400">جارٍ البحث…</p>

                    <p x-show="!recordsLoading && !records.length" x-cloak
                        class="py-6 text-center text-sm text-zinc-400">لا نتائج</p>

                    <div x-show="!recordsLoading && records.length" x-cloak class="max-h-80 space-y-2 overflow-y-auto">
                        <template x-for="record in records" :key="record.type + record.id">
                            <button type="button" @click="selectRecord(record)"
                                :class="target?.key === (record.type + '/' + record.id) ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50'"
                                class="flex w-full items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-right text-sm transition">
                                <span class="truncate font-bold" x-text="record.name"></span>
                                <span x-show="record.title || record.description || record.image_path" x-cloak
                                    class="shrink-0 rounded-lg bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">مخصّص</span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- أيقونة الموقع --}}
                <div x-show="tab === 'defaults'" x-cloak class="{{ $card }}">
                    <p class="mb-1 text-sm font-bold">أيقونة الموقع</p>
                    <p class="mb-4 text-xs text-zinc-400">
                        تظهر في تبويب المتصفح، وبجانب الموقع في نتائج البحث، وعلى شاشة الجوال عند حفظ الموقع كاختصار.
                    </p>

                    <div class="flex flex-wrap items-center gap-5">
                        {{-- محاكاة تبويب المتصفح --}}
                        <div class="rounded-t-lg bg-zinc-200 px-2 pt-2 dark:bg-zinc-800">
                            <div class="flex w-52 items-center gap-2 rounded-t-lg bg-white px-3 py-2 dark:bg-zinc-950">
                                <img :src="defaults.favicon_url" alt="" class="h-4 w-4 shrink-0 rounded-sm object-cover">
                                <span class="truncate text-xs text-zinc-600 dark:text-zinc-300">{{ config('app.name') }}</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="{{ $ghost }} cursor-pointer">
                                <span x-text="faviconUploading ? 'جارٍ الرفع…' : (defaults.favicon_is_custom ? 'استبدال الأيقونة' : 'رفع أيقونة')"></span>
                                <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden"
                                    @change="uploadFavicon($event)">
                            </label>

                            <p class="text-xs text-zinc-400" x-show="!defaults.favicon_is_custom" x-cloak>
                                الأيقونة المرفقة مع الموقع مستخدمة حاليًا.
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 text-xs leading-relaxed text-zinc-400">
                        ارفع صورة مربّعة؛ تُقصّ إلى <span x-text="defaults.favicon_size + '×' + defaults.favicon_size"></span> وتُحفظ PNG —
                        جوجل لا يعرض أيقونة غير مربّعة، ويشترط أن يكون ضلعها من مضاعفات ٤٨.
                        تُحفظ فور الرفع دون الحاجة لزر الحفظ، وقد يستغرق ظهورها في نتائج البحث أيامًا.
                    </p>
                </div>

                {{-- المحرّر --}}
                <div x-show="target" x-cloak class="{{ $card }} space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-bold" x-text="target?.label"></p>
                        <a x-show="target?.url" :href="target?.url" target="_blank" rel="noopener"
                            class="text-xs font-bold text-primary-500 hover:underline">فتح الصفحة ↗</a>
                    </div>

                    {{-- العنوان --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="{{ $label }}">العنوان</label>
                            <span class="text-[11px] font-bold" :class="counterClass(form.title, titleLimit)"
                                x-text="form.title.length + ' / ' + titleLimit"></span>
                        </div>
                        <input type="text" x-model="form.title" class="{{ $input }}"
                            :placeholder="target?.auto?.title || defaults.seo_default_title || 'اتركه فارغًا ليُستخدم النص التلقائي'">
                        <p x-show="formErrors.title" x-cloak class="{{ $error }}" x-text="formErrors.title?.[0]"></p>
                        <p x-show="!form.title && target?.auto?.title" x-cloak class="mt-1 text-xs text-zinc-400">
                            التلقائي الآن: <span x-text="target?.auto?.title"></span>
                        </p>
                    </div>

                    {{-- الوصف --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="{{ $label }}">الوصف</label>
                            <span class="text-[11px] font-bold" :class="counterClass(form.description, descriptionLimit)"
                                x-text="form.description.length + ' / ' + descriptionLimit"></span>
                        </div>
                        <textarea x-model="form.description" rows="3" class="{{ $input }}"
                            :placeholder="target?.auto?.description || defaults.seo_default_description || 'اتركه فارغًا ليُستخدم النص التلقائي'"></textarea>
                        <p x-show="formErrors.description" x-cloak class="{{ $error }}" x-text="formErrors.description?.[0]"></p>
                    </div>

                    {{-- صورة المشاركة --}}
                    <div>
                        <label class="{{ $label }}">صورة المشاركة</label>
                        <div class="flex flex-wrap items-center gap-3">
                            <img x-show="form.image_url" x-cloak :src="form.image_url" alt=""
                                class="h-16 w-auto rounded-lg border border-zinc-200 object-cover dark:border-zinc-700">

                            <label class="{{ $ghost }} cursor-pointer">
                                <span x-text="uploading ? 'جارٍ الرفع…' : (form.image_url ? 'استبدال' : 'رفع صورة')"></span>
                                <input type="file" accept="image/*" class="hidden" @change="uploadImage($event)">
                            </label>

                            <button type="button" x-show="form.image_url" x-cloak @click="clearImage()"
                                class="text-xs font-bold text-red-500 hover:underline">إزالة</button>
                        </div>
                        <p class="mt-1.5 text-xs text-zinc-400">
                            تُقصّ الصورة تلقائيًا إلى <span x-text="socialSize[0] + '×' + socialSize[1]"></span> وتُحفظ JPG —
                            وهي النسبة التي يعرضها واتساب كلافتة عريضة بدل مربّع صغير.
                        </p>
                    </div>

                    {{-- خيارات الصفحة --}}
                    <div x-show="target?.kind === 'page'" x-cloak>
                        <label class="{{ $label }}">نوع المحتوى</label>
                        <select x-model="form.og_type" class="{{ $input }}">
                            <option value="">تلقائي</option>
                            <option value="website">موقع</option>
                            <option value="article">مقال</option>
                            <option value="profile">صفحة تعريف</option>
                        </select>
                    </div>

                    <label x-show="target?.kind !== 'defaults'" x-cloak
                        class="flex items-start gap-2.5 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                        <input type="checkbox" x-model="form.noindex" class="mt-0.5 rounded border-zinc-300">
                        <span>
                            <span class="block text-sm font-bold">إخفاء من محركات البحث</span>
                            <span class="block text-xs text-zinc-400">تبقى الصفحة مفتوحة لمن يملك رابطها، لكنها لا تُفهرس.</span>
                        </span>
                    </label>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="button" @click="save()" :disabled="saving" class="{{ $primary }}">
                            <span x-show="!saving">حفظ</span>
                            <span x-show="saving" x-cloak>جارٍ الحفظ…</span>
                        </button>

                        <span x-show="saved" x-cloak class="text-sm font-bold text-emerald-600 dark:text-emerald-400">تم الحفظ ✓</span>
                    </div>
                </div>
            </div>

            {{-- ── العمود الأيسر: المعاينة ── --}}
            <div x-show="target" x-cloak class="space-y-4 lg:sticky lg:top-4 lg:self-start">

                {{-- جوجل --}}
                <div class="{{ $card }}">
                    <p class="mb-3 text-xs font-bold text-zinc-400">نتيجة البحث في جوجل</p>
                    <div class="rounded-xl bg-white p-3 dark:bg-zinc-950" dir="rtl">
                        <p class="truncate text-xs text-zinc-600 dark:text-zinc-400" x-text="previewCrumbs"></p>
                        <p class="mt-0.5 truncate text-lg text-[#1a0dab] dark:text-[#8ab4f8]" x-text="previewTitle"></p>
                        <p class="mt-0.5 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400" x-text="previewDescription"></p>
                    </div>
                    <p x-show="form.noindex" x-cloak class="mt-2 text-xs font-bold text-amber-600 dark:text-amber-400">
                        لن تظهر هذه الصفحة في نتائج البحث إطلاقًا.
                    </p>
                </div>

                {{-- واتساب --}}
                <div class="{{ $card }}">
                    <p class="mb-3 text-xs font-bold text-zinc-400">المشاركة في واتساب</p>
                    <div class="rounded-xl bg-[#0b141a] p-3">
                        <div class="ms-auto max-w-[19rem] overflow-hidden rounded-lg bg-[#005c4b]">
                            <img :src="previewImage" alt="" class="h-36 w-full object-cover">
                            <div class="px-2.5 py-2">
                                <p class="truncate text-[13px] font-bold text-white" x-text="previewTitle"></p>
                                <p class="line-clamp-2 text-[12px] text-white/70" x-text="previewDescription"></p>
                                <p class="mt-0.5 truncate text-[11px] text-white/50" x-text="previewHost"></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- فيسبوك ولينكدإن --}}
                <div class="{{ $card }}">
                    <p class="mb-3 text-xs font-bold text-zinc-400">فيسبوك ولينكدإن</p>
                    <div class="overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700">
                        <img :src="previewImage" alt="" class="h-40 w-full object-cover">
                        <div class="bg-zinc-100 px-3 py-2 dark:bg-zinc-800">
                            <p class="truncate text-[11px] uppercase text-zinc-500" x-text="previewHost"></p>
                            <p class="truncate text-sm font-bold" x-text="previewTitle"></p>
                            <p class="line-clamp-1 text-xs text-zinc-500 dark:text-zinc-400" x-text="previewDescription"></p>
                        </div>
                    </div>
                </div>

                {{-- X --}}
                <div class="{{ $card }}">
                    <p class="mb-3 text-xs font-bold text-zinc-400">‏X (تويتر)</p>
                    <div class="overflow-hidden rounded-2xl border border-zinc-300 dark:border-zinc-700">
                        <img :src="previewImage" alt="" class="h-40 w-full object-cover">
                        <div class="px-3 py-2">
                            <p class="truncate text-sm font-bold" x-text="previewTitle"></p>
                            <p class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400" x-text="previewDescription"></p>
                            <p class="mt-1 truncate text-xs text-zinc-400" x-text="previewHost"></p>
                        </div>
                    </div>
                </div>

                <p class="px-1 text-xs leading-relaxed text-zinc-400">
                    واتساب يحتفظ بمعاينة كل رابط بعد أول مشاركة، فالتعديل لا يظهر على روابط شُوركت من قبل.
                    شارك الرابط بإضافة <span class="font-mono">‎?v=2</span> في نهايته لإجباره على قراءة الوسوم من جديد.
                </p>
            </div>
        </div>
    </div>
@endsection
