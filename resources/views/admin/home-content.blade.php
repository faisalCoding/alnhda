@extends('admin.layouts.panel')

@section('title', 'محتوى الصفحة الرئيسية')
@section('heading', 'محتوى الصفحة الرئيسية')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $hint = 'mt-1 text-xs text-zinc-400';
    $card = 'rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900';
@endphp

@section('content')
    <div x-data="homeContentPage()" class="space-y-6">

        <p x-show="error" x-cloak class="rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600" x-text="error"></p>

        <template x-if="loading">
            <p class="text-sm text-zinc-400">جارٍ التحميل…</p>
        </template>

        <div x-show="!loading" x-cloak class="space-y-6">

            {{-- Hero text --}}
            <section class="{{ $card }}">
                <h2 class="mb-1 font-extrabold">نص الجزء العلوي</h2>
                <p class="mb-5 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                    اترك أي حقل فارغًا ليبقى النص الحالي كما هو — الفراغ هنا لا يعني فراغًا في الصفحة.
                    النص الرمادي داخل كل حقل هو ما تعرضه الصفحة الآن.
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="{{ $label }}">السطر العلوي (الشعار)</label>
                        <input type="text" x-model="hero.hero_eyebrow" :placeholder="placeholderFor('eyebrow')" class="{{ $input }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">العنوان الرئيسي</label>
                        <input type="text" x-model="hero.hero_title" :placeholder="placeholderFor('title')" class="{{ $input }}">
                        <p class="{{ $hint }}">
                            هذا هو <span class="font-mono">h1</span> الصفحة، وأهم سطر فيها لمحركات البحث.
                            اجعله يذكر ما تبيعه وأين.
                        </p>
                    </div>

                    <div>
                        <label class="{{ $label }}">السطر التوضيحي</label>
                        <textarea x-model="hero.hero_subtitle" :placeholder="placeholderFor('subtitle')" rows="2"
                            class="{{ $input }} leading-relaxed"></textarea>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="{{ $label }}">الزر الأساسي</label>
                            <input type="text" x-model="hero.hero_primary_label" :placeholder="placeholderFor('primary_label')" class="{{ $input }}">
                            <p class="{{ $hint }}">يفتح صفحة المشاريع.</p>
                        </div>
                        <div>
                            <label class="{{ $label }}">الزر الثانوي</label>
                            <input type="text" x-model="hero.hero_secondary_label" :placeholder="placeholderFor('secondary_label')" class="{{ $input }}">
                            <p class="{{ $hint }}">يفتح صفحة التواصل.</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Hero background --}}
            <section class="{{ $card }}">
                <h2 class="mb-1 font-extrabold">خلفية الجزء العلوي</h2>
                <p class="mb-4 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                    ارفع صورة لتظهر خلف العنوان في أعلى الصفحة. بلا صورة مرفوعة، تعرض الصفحة غلاف
                    أول مشروع في ترتيب لوحة المشاريع.
                </p>

                <div class="grid gap-4 sm:grid-cols-[220px_1fr] sm:items-start">
                    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                        <img :src="heroImageUrl" alt="خلفية الجزء العلوي" class="aspect-video w-full object-cover">
                    </div>

                    <div class="space-y-3">
                        <p class="text-xs font-bold"
                            x-text="heroImageIsUploaded ? 'الصورة الظاهرة الآن: صورة مرفوعة من هنا' : 'الصورة الظاهرة الآن: غلاف أول مشروع'"></p>

                        <div class="flex flex-wrap items-center gap-2">
                            <label
                                class="cursor-pointer rounded-xl bg-primary-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-primary-600"
                                :class="uploading && 'pointer-events-none opacity-50'">
                                <span x-show="!uploading">رفع صورة</span>
                                <span x-show="uploading" x-cloak>جارٍ الرفع…</span>
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden"
                                    @change="uploadHeroImage($event)">
                            </label>

                            <button type="button" x-show="heroImageIsUploaded" x-cloak @click="removeHeroImage()"
                                class="rounded-xl px-4 py-2 text-sm font-bold text-red-500 hover:bg-red-500/10">
                                حذف الصورة المرفوعة
                            </button>
                        </div>

                        <p class="{{ $hint }}">
                            يُفضّل صورة عريضة لا يقل عرضها عن ١٦٠٠ بكسل. تُصغَّر تلقائيًا إلى ٢٠٠٠ بكسل
                            وتُحوَّل إلى WEBP، لأن هذه أول صورة يحمّلها كل زائر.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Section visibility --}}
            <section class="{{ $card }}">
                <h2 class="mb-1 font-extrabold">أقسام الصفحة</h2>
                <p class="mb-4 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                    أطفئ ما لا تريد إظهاره. القسم المطفأ يختفي من الصفحة ومن البيانات المنظمة معًا —
                    فلا نَعِد جوجل بمحتوى لا يجده الزائر.
                </p>

                <div class="grid gap-2 sm:grid-cols-2">
                    <template x-for="section in sections" :key="section.key">
                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <span class="text-sm font-bold" x-text="section.label"></span>
                            <input type="checkbox" :checked="section.visible" @change="toggleSection(section.key)"
                                class="h-5 w-5 rounded border-zinc-300 text-primary-500">
                        </label>
                    </template>
                </div>
            </section>

            {{-- Guarantees --}}
            <section class="{{ $card }}">
                <h2 class="mb-1 font-extrabold">الضمانات</h2>
                <p class="mb-4 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                    تظهر في الصفحة الرئيسية وفي أول سؤال من الأسئلة المتكررة.
                </p>

                <template x-if="usingProjectGuarantees">
                    <div class="mb-4 rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700">
                        <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            القائمة فارغة، فالصفحة تعرض الآن ضمانات المشاريع نفسها — أي ما كتبته داخل كل مشروع.
                            اكتب قائمة هنا لتحل محلها في الصفحة الرئيسية وحدها (صفحات المشاريع لا تتأثر).
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <template x-for="text in guaranteeDefaults" :key="text">
                                <span class="rounded-lg bg-zinc-100 px-2.5 py-1 text-xs dark:bg-zinc-800" x-text="text"></span>
                            </template>
                        </div>
                        <button type="button" @click="copyProjectGuarantees()" x-show="guaranteeDefaults.length"
                            class="mt-3 text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">
                            ابدأ من ضمانات المشاريع
                        </button>
                    </div>
                </template>

                <div class="space-y-2">
                    <template x-for="(guarantee, index) in guarantees" :key="index">
                        <div class="flex items-center gap-2">
                            <input type="text" x-model="guarantees[index]" class="{{ $input }}"
                                placeholder="مثال: ضمان ٢٠ سنة على الهيكل الخرساني">
                            <button type="button" @click="removeGuarantee(index)"
                                class="rounded-lg p-2.5 text-red-500 hover:bg-red-500/10" aria-label="حذف الضمان">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                <button type="button" @click="addGuarantee()"
                    class="mt-3 text-sm font-bold text-primary-600 hover:underline dark:text-primary-300">
                    + إضافة ضمان
                </button>
            </section>

            {{-- Save --}}
            <div class="flex items-center gap-3">
                <button type="button" @click="saveHero()" :disabled="saving"
                    class="rounded-xl bg-primary-500 px-6 py-3 text-sm font-extrabold text-white hover:bg-primary-600 disabled:opacity-50">
                    <span x-show="!saving">حفظ النصوص والأقسام والضمانات</span>
                    <span x-show="saving" x-cloak>جارٍ الحفظ…</span>
                </button>
                <span x-show="saved" x-cloak class="text-sm font-bold text-primary-600 dark:text-primary-300">تم الحفظ ✓</span>
            </div>

            {{-- Questions --}}
            <section class="{{ $card }}">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-extrabold">الأسئلة المتكررة</h2>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            تظهر في أسفل الصفحة الرئيسية، وتُنشر أيضًا للزواحف ولمحركات الذكاء الاصطناعي.
                        </p>
                    </div>
                    <button type="button" @click="openCreate()"
                        class="rounded-xl bg-primary-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-primary-600">
                        سؤال جديد
                    </button>
                </div>

                <template x-if="usingDerivedFaq">
                    <div class="rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700">
                        <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            لم تكتب أسئلة بعد، والصفحة تعرض الآن أسئلة يكتبها الموقع لنفسه من بيانات المشاريع والوحدات.
                            استوردها لتصير ملكك وتعدّلها كما تشاء — أو اكتب أسئلتك من الصفر.
                        </p>
                        <button type="button" @click="importDerived()" :disabled="saving"
                            class="mt-3 rounded-xl bg-zinc-100 px-4 py-2 text-xs font-bold hover:bg-zinc-200 disabled:opacity-50 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                            استيراد الأسئلة الحالية (<span x-text="faqDefaults.length"></span>)
                        </button>
                    </div>
                </template>

                <div class="flex flex-col gap-3">
                    <template x-for="(entry, index) in faq" :key="entry.id">
                        <article class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="font-bold" x-text="entry.question"></h3>
                                    <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400"
                                        x-text="entry.answer"></p>
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" @click="move(entry, -1)" :disabled="index === 0"
                                        class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 disabled:opacity-30 dark:hover:bg-zinc-800"
                                        aria-label="تحريك لأعلى">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                        </svg>
                                    </button>
                                    <button type="button" @click="move(entry, 1)" :disabled="index === faq.length - 1"
                                        class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 disabled:opacity-30 dark:hover:bg-zinc-800"
                                        aria-label="تحريك لأسفل">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <button type="button" @click="openEdit(entry)"
                                        class="rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-bold hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                                        تعديل
                                    </button>
                                    <button type="button" @click="removeQuestion(entry)"
                                        class="rounded-lg px-3 py-1.5 text-xs font-bold text-red-500 hover:bg-red-500/10">
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </section>
        </div>

        {{-- Question form --}}
        <div x-show="showForm" x-cloak class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showForm = false"></div>

            <div class="panel-slide-enter absolute inset-y-0 left-0 flex w-full max-w-2xl flex-col bg-white shadow-2xl dark:bg-zinc-900"
                x-trap.noscroll="showForm" @keydown.escape.window="showForm = false">

                <header class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-extrabold" x-text="form.id ? 'تعديل سؤال' : 'سؤال جديد'"></h2>
                    <button type="button" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        @click="showForm = false">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                    <div>
                        <label class="{{ $label }}">السؤال</label>
                        <input type="text" x-model="form.question" class="{{ $input }}"
                            placeholder="مثال: هل تقبلون التمويل العقاري؟">
                        <p class="{{ $error }}" x-show="formErrors.question" x-text="formErrors.question"></p>
                        <p class="{{ $hint }}">اكتبه بالصيغة التي يسأل بها العميل فعلًا، لا بصيغة رسمية.</p>
                    </div>

                    <div>
                        <label class="{{ $label }}">الجواب</label>
                        <textarea x-model="form.answer" rows="6" class="{{ $input }} leading-relaxed"
                            placeholder="ابدأ بالإجابة مباشرة، ثم التفصيل…"></textarea>
                        <p class="{{ $error }}" x-show="formErrors.answer" x-text="formErrors.answer"></p>
                        <p class="{{ $hint }}">
                            اجعله مكتفيًا بذاته: النموذج اللغوي يقتبس الفقرة وحدها بلا ما حولها،
                            فجواب يبدأ بـ«نعم» فقط لا يفيد أحدًا.
                        </p>
                    </div>
                </div>

                <footer class="flex items-center gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <button type="button" @click="saveQuestion()" :disabled="saving"
                        class="flex-1 rounded-xl bg-primary-500 px-4 py-3 text-sm font-extrabold text-white hover:bg-primary-600 disabled:opacity-50">
                        حفظ
                    </button>
                    <button type="button" @click="showForm = false"
                        class="rounded-xl border border-zinc-300 px-4 py-3 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        إلغاء
                    </button>
                </footer>
            </div>
        </div>
    </div>
@endsection
