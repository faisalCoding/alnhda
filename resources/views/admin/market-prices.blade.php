@extends('admin.layouts.panel')

@section('title', 'أسعار السوق')
@section('heading', 'أسعار السوق')

@php
    $card = 'rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900';
    $tile = 'rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900';
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800';
    $select = 'rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800';
    $pill = 'rounded-lg px-4 py-1.5 text-sm font-bold transition-colors';
    $pillOn = 'bg-primary-500 text-white';
    $pillOff = 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800';
    $primaryButton = 'flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white hover:bg-primary-600';
    $ghostButton = 'flex items-center gap-1.5 rounded-xl border border-zinc-300 px-4 py-2 text-sm font-bold text-zinc-600 hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $notice = 'rounded-2xl border border-dashed border-amber-400 bg-amber-50 p-6 dark:bg-amber-500/10';
    $noticeLink = 'rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-amber-700 hover:underline dark:bg-zinc-900 dark:text-amber-300';
    $tiles = [
        ['deals', 'الصفقات', 'number', ''],
        ['value', 'قيمة الصفقات', 'money', 'ر.س'],
        ['median', 'وسيط سعر المتر', 'rate', 'ر.س/م²'],
        ['median_price', 'وسيط قيمة الصفقة', 'money', 'ر.س'],
    ];
    $metrics = [['median', 'سعر المتر'], ['deals', 'عدد الصفقات'], ['value', 'قيمة الصفقات']];
    // The board: one square per group of what the screen shows. Each is an icon, a title and what the group answers.
    $board = [
        ['kingdom', 'المملكة', 'أسعار المملكة ومناطقها ربعًا بربع', 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418'],
        ['city', 'المدينة وأحياؤها', 'أسعار المدينة وترتيب أحيائها وتاريخ كل حي', 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z'],
        ['deeds', 'الصفقات الفردية', 'كل صفقة بحيّها ومساحتها وسعرها، مع تصفية حرة', 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z'],
        ['types', 'حسب نوع العقار', 'شقة وفيلا ودور وأرض: متوسط كل نوع وتاريخه', 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
        ['units', 'وحداتنا مقابل السوق', 'كل وحدة من مشاريعنا أمام سعر السوق لنفس نوعها', 'M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z'],
        ['offers', 'وحدات السوق المضافة يدويًا', 'أسعار معروضة رصدتها في السوق، تُقارَن بها وحداتنا', 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3ZM6 6h.008v.008H6V6Z'],
        ['files', 'الملفات والتحديث', 'رفع ملفات المنصة وما لم يُرفع منها بعد', 'M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z'],
    ];
@endphp

@section('content')
    <div x-data="marketPricesPage()" class="space-y-6 rounded-2xl transition"
        :class="dragging && 'outline-2 outline-offset-8 outline-dashed outline-primary-500'"
        @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)">

        <p x-show="error" x-cloak class="rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600" x-text="error"></p>
        <p x-show="loading" class="text-sm text-zinc-400">جارٍ التحميل…</p>

        {{-- The board: one square per group. Nothing below it is shown until a square is opened. --}}
        <div x-show="!loading && data && view === ''" x-cloak class="mx-auto grid max-w-4xl grid-cols-2 gap-4 md:grid-cols-3">
            @foreach ($board as [$key, $title, $text, $icon])
                <button type="button" @click="go('{{ $key }}')"
                    class="group relative flex aspect-square flex-col justify-between rounded-2xl border border-zinc-200 bg-white p-4 text-right transition hover:border-primary-500 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-500/10 text-primary-600 transition group-hover:bg-primary-500 group-hover:text-white dark:text-primary-300 sm:h-16 sm:w-16">
                        <svg class="h-6 w-6 sm:h-8 sm:w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                        </svg>
                    </span>

                    @if ($key === 'files')
                        <span class="absolute left-4 top-4 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-extrabold text-white"
                            x-show="filesNeedingAttention" x-cloak x-text="number(filesNeedingAttention)"></span>
                    @endif

                    <span class="block">
                        <span class="block font-extrabold sm:text-lg">{{ $title }}</span>
                        <span class="mt-1 hidden text-xs leading-relaxed text-zinc-500 dark:text-zinc-400 sm:block">{{ $text }}</span>
                        <span class="mt-2 block truncate text-xs font-bold text-primary-600 dark:text-primary-300 sm:text-sm"
                            x-text="tileFigure('{{ $key }}')"></span>
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Inside a group: the way back, and the filters that group reads by --}}
        <div x-show="view !== ''" x-cloak class="flex flex-wrap items-center gap-3">
            <button type="button" class="{{ $ghostButton }}" @click="go('')">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
                كل المجموعات
            </button>

            <select x-show="hasData && shows('quarter')" x-model="quarter" @change="load()" class="{{ $select }}" aria-label="الربع">
                <template x-for="item in quarters" :key="item.key">
                    <option :value="item.key" :selected="item.key === quarter" x-text="quarterLabel(item)"></option>
                </template>
            </select>

            <select x-show="shows('city') && data?.cities?.length" x-model="city" @change="setCity(city)" class="{{ $select }}" aria-label="المدينة">
                <template x-for="item in (data?.cities ?? [])" :key="item.key">
                    <option :value="item.key" :selected="item.key === city"
                        x-text="`${item.label} (${number(item.deals)})`"></option>
                </template>
            </select>

            <div x-show="hasData && shows('classification')" class="flex flex-wrap items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-800">
                <button type="button" @click="setClassification('')" class="{{ $pill }}"
                    :class="classification === '' ? '{{ $pillOn }}' : '{{ $pillOff }}'">الكل</button>
                <template x-for="value in classifications" :key="value">
                    <button type="button" @click="setClassification(value)" class="{{ $pill }}"
                        :class="classification === value ? '{{ $pillOn }}' : '{{ $pillOff }}'" x-text="value"></button>
                </template>
            </div>
        </div>

        {{-- Size of what was sold: the nearest thing the files have to a property type --}}
        <div class="flex flex-wrap items-center gap-3" x-show="hasData && shows('band')" x-cloak>
            <span class="text-xs font-bold text-zinc-500">المساحة</span>
            <div class="flex flex-wrap items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-800">
                <button type="button" @click="setBand('')" class="{{ $pill }}"
                    :class="band === '' ? '{{ $pillOn }}' : '{{ $pillOff }}'">الكل</button>
                <template x-for="item in bands" :key="item.key">
                    <button type="button" @click="setBand(item.key)" class="{{ $pill }}"
                        :class="band === item.key ? '{{ $pillOn }}' : '{{ $pillOff }}'" x-text="item.label"></button>
                </template>
            </div>
        </div>

        {{-- The files group: uploading, and asking the portal what it has published --}}
        <div x-show="view === 'files'" x-cloak class="space-y-6">
            <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="{{ $ghostButton }}" :disabled="checking" @click="check()"
                x-text="checking ? 'جارٍ سؤال المنصة…' : 'تحقق من المنصة'"></button>

            <label class="{{ $primaryButton }} cursor-pointer" :class="uploading && 'pointer-events-none opacity-50'">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
            <span x-text="uploading ? 'جارٍ الرفع…' : 'رفع ملفات الصفقات'"></span>
            <input type="file" accept=".csv,text/csv" multiple class="hidden" @change="upload($event)">
            </label>
            </div>

            <p class="text-xs text-zinc-400" x-show="catalogueCheckedAt" x-cloak
                x-text="'آخر تحديث لقائمة الملفات المنشورة على المنصة: ' + catalogueCheckedAt"></p>
    
            <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400" x-show="data?.portal_unreachable" x-cloak>
                هذا الخادم لا يصل إلى منصة البيانات المفتوحة، فقائمة الملفات المنشورة تُحدَّث من جهاز داخل المملكة بالأمر
                <span class="font-mono" dir="ltr">php artisan market:check --push</span>. رفع الملفات لا يتأثر.
            </p>
    
            <p x-show="checkMessage" x-cloak class="rounded-xl bg-zinc-200/60 px-4 py-3 text-sm font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                x-text="checkMessage"></p>

            {{-- What the last upload did, file by file --}}
            <section class="{{ $card }}" x-show="uploads.length" x-cloak>
                <h2 class="mb-4 font-extrabold">الملفات المرفوعة</h2>
                <ul class="flex flex-col gap-3">
                    <template x-for="entry in uploads" :key="entry.name">
                        <li class="text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="truncate font-medium" dir="ltr" x-text="entry.name"></span>
                                <span class="shrink-0 text-xs font-bold"
                                    :class="{
                                        'text-zinc-400': entry.status === 'waiting',
                                        'text-amber-600': entry.status === 'uploading',
                                        'text-primary-600 dark:text-primary-300': entry.status === 'done',
                                        'text-red-500': entry.status === 'failed',
                                    }"
                                    x-text="{ waiting: 'في الانتظار', uploading: entry.progress < 100 ? `يُرفع ${entry.progress}٪` : 'يُحسب…', done: 'تم', failed: 'لم يُقبل' }[entry.status]"></span>
                            </div>
                            <p class="mt-1 text-xs" :class="entry.status === 'failed' ? 'text-red-500' : 'text-zinc-500'"
                                x-show="entry.message" x-text="entry.message"></p>
                        </li>
                    </template>
                </ul>
            </section>
        </div>

        <template x-if="!loading && data">
            <div class="space-y-6">

                {{-- The files group, continued: what is waiting, replaced or out of date --}}
                <div x-show="view === 'files'" class="space-y-6">
                    {{-- Quarters the ministry has published that nobody has uploaded yet --}}
                    <template x-if="waiting.length">
                        <div class="{{ $notice }}">
                            <h2 class="mb-2 font-extrabold text-amber-700 dark:text-amber-400"
                                x-text="`${number(waiting.length)} من الأرباع المنشورة لم يُرفع بعد`"></h2>
                            <p class="mb-4 text-sm leading-relaxed text-amber-700/80 dark:text-amber-300/80">
                                اضغط على الربع لتنزيل ملف CSV مباشرة، ثم اسحب الملفات إلى هذه الصفحة أو ارفعها من الزر أعلاه.
                                إن لم يبدأ التنزيل فافتح «صفحته» على المنصة ونزّله من هناك.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="item in waiting" :key="item.key">
                                    <span class="{{ $noticeLink }} flex items-center gap-2 hover:no-underline">
                                        <a :href="item.download_url ?? item.portal_url" target="_blank" rel="noopener"
                                            class="hover:underline" x-text="quarterLabel(item)"></a>
                                        <a :href="item.portal_url" target="_blank" rel="noopener" x-show="item.download_url"
                                            class="font-medium text-amber-700/60 hover:underline dark:text-amber-300/60">صفحته</a>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
    
                    {{-- Files the ministry replaced after they were uploaded here --}}
                    <template x-if="changed.length">
                        <div class="{{ $notice }}">
                            <h2 class="mb-2 font-extrabold text-amber-700 dark:text-amber-400">الوزارة حدّثت ملفات بعد رفعها</h2>
                            <p class="mb-4 text-sm leading-relaxed text-amber-700/80 dark:text-amber-300/80">
                                نزّل النسخة الجديدة وارفعها، فتحلّ محل القديمة.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="item in changed" :key="item.key">
                                    <a :href="item.download_url ?? item.portal_url" target="_blank" rel="noopener" class="{{ $noticeLink }}"
                                        x-text="`${quarterLabel(item)} — حُدّث ${day(item.file_updated_at)}`"></a>
                                </template>
                            </div>
                        </div>
                    </template>
    
                    {{-- Quarters counted before bands and ranges were kept --}}
                    <template x-if="outdated.length">
                        <div class="{{ $notice }}">
                            <h2 class="mb-2 font-extrabold text-amber-700 dark:text-amber-400"
                                x-text="`${number(outdated.length)} من الأرباع رُفع قبل آخر تحديث للتفاصيل`"></h2>
                            <p class="mb-4 text-sm leading-relaxed text-amber-700/80 dark:text-amber-300/80">
                                أرقامها الإجمالية ظاهرة، لكن ينقصها ما أُضيف بعد رفعها: فئات المساحة ونطاق الأسعار والصفقات
                                الفردية. ارفع ملفاتها مرة أخرى لتُحسب من جديد.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="item in outdated" :key="item.key">
                                    <a :href="item.download_url ?? item.portal_url ?? data.publisher_url" target="_blank" rel="noopener"
                                        class="{{ $noticeLink }}" x-text="quarterLabel(item)"></a>
                                </template>
                            </div>
                        </div>
                    </template>
    
                    {{-- Nothing imported yet --}}
                    <template x-if="!hasData">
                        <div class="rounded-2xl border border-dashed border-zinc-300 p-8 dark:border-zinc-700">
                            <h2 class="mb-2 font-extrabold">لا توجد بيانات بعد</h2>
                            <p class="mb-4 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                                هذه الصفحة تقرأ صفقات البيع التي تنشرها وزارة العدل كل ربع سنة على منصة البيانات المفتوحة.
                                المنصة لا تسلّم الملفات إلا لمتصفح يفتحه شخص، فيُنزَّل كل ربع يدويًا ثم يُرفع هنا.
                            </p>
                            <ol class="list-decimal space-y-1.5 ps-5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                                <li>افتح <a :href="data.publisher_url" target="_blank" rel="noopener" class="font-bold text-primary-600 hover:underline dark:text-primary-300">صفحة وزارة العدل على منصة البيانات المفتوحة</a> وابحث عن «الصفقات العقارية».</li>
                                <li>من كل ربع، نزّل الملف <span class="font-mono text-xs" dir="ltr">Transactions sale for real estate CSV</span>.</li>
                                <li>ارفع الملفات من زر «رفع ملفات الصفقات» أعلاه، كما هي دون فتحها في Excel.</li>
                            </ol>
                        </div>
                    </template>

                    <template x-if="types?.waiting.length">
                        <div class="{{ $notice }}">
                            <h3 class="mb-2 font-extrabold text-amber-700 dark:text-amber-400"
                                x-text="`${number(types.waiting.length)} من ملفات مؤشرات الهيئة لم يُرفع بعد`"></h3>
                            <p class="mb-4 text-sm leading-relaxed text-amber-700/80 dark:text-amber-300/80">
                                تُنزَّل وتُرفع مثل ملفات الصفقات، ومن الزر نفسه: الصفحة تتعرّف على نوع الملف.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="item in types.waiting" :key="item.key + item.region">
                                    <span class="{{ $noticeLink }} flex items-center gap-2 hover:no-underline">
                                        <a :href="item.download_url ?? item.portal_url" target="_blank" rel="noopener"
                                            class="hover:underline" x-text="`${item.region} — ${quarterLabel(item)}`"></a>
                                        <a :href="item.portal_url" target="_blank" rel="noopener" x-show="item.download_url"
                                            class="font-medium text-amber-700/60 hover:underline dark:text-amber-300/60">صفحته</a>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="types?.changed.length">
                        <div class="{{ $notice }}">
                            <h3 class="mb-2 font-extrabold text-amber-700 dark:text-amber-400">الهيئة حدّثت ملفات بعد رفعها</h3>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="item in types.changed" :key="item.key + item.region">
                                    <a :href="item.download_url ?? item.portal_url" target="_blank" rel="noopener" class="{{ $noticeLink }}"
                                        x-text="`${item.region} — ${quarterLabel(item)} — حُدّث ${day(item.file_updated_at)}`"></a>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                @include('admin.partials.manual-market-units')

                        {{-- The company's own units beside what the market paid for the same kind of property --}}
                        <template x-if="projects?.projects.length">
                            <section x-show="view === 'units'" class="space-y-4">
                                <h2 class="text-lg font-extrabold">وحداتنا مقابل السوق</h2>

                                <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                    كل وحدة تُقارَن بسعر السوق لنفس نوعها. اختر لكل مشروع حيّه، ولكل وحدة نوعها إن لم يكن التخمين صحيحًا.
                                    «متوسط السوق لنفس النوع» من مؤشرات الهيئة العامة للعقار: للحي حين نشرته الهيئة، وللمدينة كلها غير ذلك.
                                    «صفقات مماثلة بالمساحة» من صكوك وزارة العدل القريبة من مساحة الوحدة (أقل أو أكثر بالخُمس) خلال آخر
                                    4 أرباع، وهي لا تميّز النوع. «عروض مماثلة» من وحدات السوق المضافة يدويًا: نفس النوع ومساحة قريبة، مما
                                    رُصد خلال آخر سنة، وهي أسعار معروضة لا صفقات تمّت. اختر أدناه المرجع الذي يُحسب منه «الفرق».
                                </p>

                                {{-- Which of the three the verdict is measured against; all three stay shown --}}
                                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="مرجع الفرق">
                                    <span class="text-xs font-bold text-zinc-500">احسب الفرق عن</span>
                                    <div class="flex flex-wrap items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-800">
                                        @foreach (['kind' => 'متوسط الهيئة لنفس النوع', 'deeds' => 'صفقات وزارة العدل', 'offers' => 'العروض المضافة يدويًا'] as $reference => $referenceLabel)
                                            <button type="button" class="{{ $pill }}" @click="setGapReference('{{ $reference }}')"
                                                :class="gapReference === '{{ $reference }}' ? '{{ $pillOn }}' : '{{ $pillOff }}'"
                                                :aria-pressed="gapReference === '{{ $reference }}'">{{ $referenceLabel }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400" x-show="gapReference === 'deeds'" x-cloak>
                                    صفقات الوزارة لا تميّز نوع العقار، فالمقارنة بها تجمع الأراضي والوحدات المبنية القريبة من المساحة نفسها.
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400" x-show="gapReference === 'offers'" x-cloak>
                                    العروض أسعار يطلبها البائعون، وعادةً أعلى مما تُباع به فعلًا. يُشترط 3 عقارات على الأقل للمقارنة.
                                </p>

                                <p x-show="!projects.has_offers" x-cloak class="text-xs text-zinc-500 dark:text-zinc-400">
                                    لا عروض مرصودة لهذه المدينة بعد.
                                    <button type="button" class="font-bold text-primary-600 hover:underline dark:text-primary-300" @click="go('offers')">أضف وحدات رأيتها في السوق</button>
                                    لتظهر في عمود «عروض مماثلة».
                                </p>

                                <p x-show="!projects.has_type_figures" x-cloak
                                    class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                    لم تُرفع ملفات مؤشرات الهيئة بعد، فلا سعر للسوق حسب النوع. ارفعها لتظهر المقارنة بنفس النوع.
                                </p>

                                <p x-show="projectsError" x-cloak class="rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600" x-text="projectsError"></p>

                                <div class="{{ $card }}">
                                    <div class="sheet-wrap is-inset">
                                        <table class="sheet">
                                            <thead>
                                                <tr>
                                                    <th>الوحدة</th>
                                                    <th>نوعها</th>
                                                    <th>المساحة (م²)</th>
                                                    <th>السعر (ر.س)</th>
                                                    <th>سعر المتر عندنا</th>
                                                    <th :class="gapReference === 'kind' && 'bg-primary-500/10'">متوسط السوق لنفس النوع</th>
                                                    <th>أدنى – أعلى (ر.س/م²)</th>
                                                    <th :class="gapReference === 'deeds' && 'bg-primary-500/10'">صفقات مماثلة بالمساحة</th>
                                                    <th :class="gapReference === 'offers' && 'bg-primary-500/10'">عروض مماثلة</th>
                                                    <th x-text="`الفرق عن ${gapLabel()}`">الفرق</th>
                                                </tr>
                                            </thead>

                                            <template x-for="project in projects.projects" :key="project.id">
                                                <tbody>
                                                    <tr class="bg-zinc-50 dark:bg-zinc-800/60">
                                                        <td colspan="10">
                                                            <div class="flex flex-wrap items-center gap-3">
                                                                <span class="font-extrabold" x-text="project.name"></span>
                                                                <span class="text-[11px] text-zinc-400" x-text="project.location"></span>
                                                                <span class="text-[11px] font-bold text-zinc-500" x-text="unitCount(project)"></span>

                                                                <label class="mr-auto flex items-center gap-2 text-[11px] font-bold text-zinc-500">
                                                                    الحي في الصفقات
                                                                    <select class="{{ $select }} py-1" :disabled="placing === project.id"
                                                                        @change="placeProject(project, $event.target.value)">
                                                                        <option value="" :selected="!project.district">— لم يُحدَّد —</option>
                                                                        <template x-for="item in projects.districts" :key="item.key">
                                                                            <option :value="item.key" :selected="item.key === project.district" x-text="item.label"></option>
                                                                        </template>
                                                                    </select>
                                                                </label>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <template x-for="unit in project.units" :key="unit.id">
                                                        <tr>
                                                            <td class="font-bold" x-text="unit.name"></td>
                                                            <td>
                                                                <select class="{{ $select }} py-1" :disabled="placing === `unit-${unit.id}`"
                                                                    @change="setUnitKind(unit, $event.target.value)" aria-label="نوع الوحدة">
                                                                    <option value="" :selected="!unit.kind || unit.kind_source !== 'set'"
                                                                        x-text="unit.kind && unit.kind_source !== 'set' ? `${unit.kind} (تخمين)` : '— لم يُحدَّد —'"></option>
                                                                    <template x-for="kind in projects.kinds" :key="kind">
                                                                        <option :value="kind" :selected="unit.kind_source === 'set' && kind === unit.kind" x-text="kind"></option>
                                                                    </template>
                                                                </select>
                                                            </td>
                                                            <td x-text="unit.area ? number(unit.area) : '—'"></td>
                                                            <td x-text="unit.price ? number(unit.price) : '—'"></td>
                                                            <td class="font-bold" x-text="rate(unit.rate)"></td>
                                                            <td :class="gapReference === 'kind' && 'bg-primary-500/5'">
                                                                <span x-text="rate(unit.market.kind?.average)"></span>
                                                                <span class="block text-[11px] text-zinc-400" x-text="scopeNote(unit.market.kind, project)"></span>
                                                            </td>
                                                            <td class="text-zinc-500" x-text="extremes(unit.market.kind)"></td>
                                                            <td :class="gapReference === 'deeds' && 'bg-primary-500/5'">
                                                                <span x-text="rate(unit.market.similar?.median)"></span>
                                                                <span class="block text-[11px] text-zinc-400" x-show="unit.market.similar"
                                                                    x-text="countCaption(unit.market.similar, project, 'صفقة')"></span>
                                                            </td>
                                                            <td :class="gapReference === 'offers' && 'bg-primary-500/5'">
                                                                <span x-text="rate(unit.market.offers?.median)"></span>
                                                                <span class="block text-[11px] text-zinc-400" x-show="unit.market.offers"
                                                                    x-text="countCaption(unit.market.offers, project, 'وحدة')"></span>
                                                            </td>
                                                            <td class="font-bold"
                                                                :class="{ 'text-red-500': gap(unit)?.direction === 'above', 'text-primary-600 dark:text-primary-300': gap(unit)?.direction === 'below', 'text-zinc-400': !gap(unit) || gap(unit).direction === 'level' }">
                                                                <span x-text="gap(unit)?.label ?? '—'"></span>
                                                                <span class="block text-[11px] font-normal text-zinc-400" x-show="!gap(unit)" x-text="gapMissing(unit)"></span>
                                                            </td>
                                                        </tr>
                                                    </template>

                                                    <tr x-show="!project.units.length">
                                                        <td colspan="10" class="text-zinc-400">لا وحدات في هذا المشروع.</td>
                                                    </tr>
                                                </tbody>
                                            </template>
                                        </table>
                                    </div>
                                </div>
                            </section>
                        </template>

                        <template x-if="view === 'units' && !projects?.projects.length">
                            <p class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm leading-relaxed text-zinc-400 dark:border-zinc-700">
                                لا مشاريع مسجّلة للمقارنة بعد.
                            </p>
                        </template>

                <template x-if="hasData">
                    <div class="space-y-6">

                        <p x-show="['kingdom', 'city'].includes(view)" class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            المصدر: صكوك البيع المسجلة لدى وزارة العدل. الملفات لا تذكر نوع العقار، والمساحة أقرب دليل عليه:
                            ما دون 300 م² أغلبه شقق، وما فوقها أغلبه قطع أراضٍ. النطاق هو ما دفعه النصف الأوسط من الصفقات للمتر.
                            <span x-show="comparedWith" x-text="`نسب التغيّر مقارنةً بـ${comparedWith}.`"></span>
                            <span x-show="!comparedWith">لا يوجد ربع مقابل من العام السابق للمقارنة.</span>
                        </p>

                        {{-- The Kingdom --}}
                        <section x-show="view === 'kingdom'" class="space-y-4">
                            <h2 class="text-lg font-extrabold">المملكة</h2>

                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ($tiles as [$key, $label, $format, $unit])
                                    <div class="{{ $tile }}">
                                        <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                                        <p class="mt-2 text-2xl font-extrabold">
                                            <span x-text="{{ $format }}(data.country.current?.{{ $key }})"></span>
                                            <span class="text-sm font-bold text-zinc-400">{{ $unit }}</span>
                                        </p>
                                        @if ($key === 'median')
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"
                                                x-text="'النطاق ' + range(data.country.current)"></p>
                                        @endif
                                        <template x-if="change(data.country.current?.{{ $key }}, data.country.previous?.{{ $key }})">
                                            <p class="mt-1 text-xs font-bold"
                                                :class="changeClass(data.country.current?.{{ $key }}, data.country.previous?.{{ $key }})"
                                                x-text="change(data.country.current?.{{ $key }}, data.country.previous?.{{ $key }}).label + ' عن العام السابق'"></p>
                                        </template>
                                    </div>
                                @endforeach
                            </div>

                            <div class="{{ $card }}">
                                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                                    <h3 class="font-extrabold">المملكة ربعًا بربع</h3>
                                    <div class="flex items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-800">
                                        @foreach ($metrics as [$key, $label])
                                            <button type="button" @click="metric = '{{ $key }}'" class="{{ $pill }}"
                                                :class="metric === '{{ $key }}' ? '{{ $pillOn }}' : '{{ $pillOff }}'">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </div>

                                @include('admin.partials.market-chart', ['series' => 'data.country.series'])
                            </div>

                            <div class="{{ $card }}">
                                <h3 class="mb-4 font-extrabold">المناطق</h3>
                                <div class="sheet-wrap is-inset">
                                    <table class="sheet">
                                        <thead>
                                            <tr>
                                                <th>المنطقة</th>
                                                <th>الصفقات</th>
                                                <th>القيمة (ر.س)</th>
                                                <th>وسيط سعر المتر</th>
                                                <th>التغيّر السنوي</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="row in data.regions" :key="row.key">
                                                <tr>
                                                    <td class="font-bold" x-text="row.label"></td>
                                                    <td x-text="number(row.deals)"></td>
                                                    <td x-text="money(row.value)"></td>
                                                    <td x-text="rate(row.median)"></td>
                                                    <td class="font-bold" :class="changeClass(row.median, row.previous_median)"
                                                        x-text="change(row.median, row.previous_median)?.label ?? '—'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        {{-- One city --}}
                        <section x-show="view === 'city'" class="space-y-4">
                            <h2 class="text-lg font-extrabold" x-text="data.city.label"></h2>

                            <template x-if="!data.city.current">
                                <p class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm text-zinc-400 dark:border-zinc-700">
                                    لا صفقات في هذه المدينة بهذا التصنيف خلال الربع المختار.
                                </p>
                            </template>

                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" x-show="data.city.current">
                                @foreach ($tiles as [$key, $label, $format, $unit])
                                    <div class="{{ $tile }}">
                                        <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                                        <p class="mt-2 text-2xl font-extrabold">
                                            <span x-text="{{ $format }}(data.city.current?.{{ $key }})"></span>
                                            <span class="text-sm font-bold text-zinc-400">{{ $unit }}</span>
                                        </p>
                                        @if ($key === 'median')
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"
                                                x-text="'النطاق ' + range(data.city.current)"></p>
                                        @endif
                                        <template x-if="change(data.city.current?.{{ $key }}, data.city.previous?.{{ $key }})">
                                            <p class="mt-1 text-xs font-bold"
                                                :class="changeClass(data.city.current?.{{ $key }}, data.city.previous?.{{ $key }})"
                                                x-text="change(data.city.current?.{{ $key }}, data.city.previous?.{{ $key }}).label + ' عن العام السابق'"></p>
                                        </template>
                                    </div>
                                @endforeach
                            </div>

                            <div class="{{ $card }}">
                                <h3 class="mb-6 font-extrabold" x-text="`${data.city.label} ربعًا بربع`"></h3>
                                @include('admin.partials.market-chart', ['series' => 'data.city.series'])
                            </div>

                            {{-- The district a row was opened for --}}
                            <template x-if="data.district">
                                <div class="rounded-2xl border border-primary-500/30 bg-primary-500/5 p-6">
                                    <div class="mb-6 flex items-center justify-between gap-3">
                                        <h3 class="font-extrabold" x-text="`حي ${data.district.label} ربعًا بربع`"></h3>
                                        <button type="button" @click="openDistrict(district)"
                                            class="text-xs font-bold text-zinc-500 hover:underline">إغلاق</button>
                                    </div>
                                    @include('admin.partials.market-chart', ['series' => 'data.district.series'])
                                </div>
                            </template>

                            <div class="{{ $card }}">
                                <div class="mb-4 flex flex-wrap items-center gap-3">
                                    <h3 class="font-extrabold">الأحياء</h3>

                                    <input type="search" x-model="search" placeholder="بحث باسم الحي…"
                                        class="{{ $select }} w-full max-w-xs">

                                    <select x-model="sort" class="{{ $select }}" aria-label="الترتيب">
                                        <option value="deals">الأكثر صفقات</option>
                                        <option value="median">الأعلى سعرًا للمتر</option>
                                        <option value="value">الأعلى قيمة</option>
                                        <option value="change">الأكثر ارتفاعًا</option>
                                    </select>

                                    <label class="flex items-center gap-2 text-xs font-bold text-zinc-500" x-show="hiddenDistricts" x-cloak>
                                        <input type="checkbox" x-model="hideThin" class="rounded border-zinc-300 text-primary-500">
                                        <span x-text="`إخفاء ${number(hiddenDistricts)} حيًّا بأقل من ${number(thinMarket)} صفقات`"></span>
                                    </label>
                                </div>

                                <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">اضغط على حي لعرض تاريخه.</p>

                                <div class="sheet-wrap is-inset">
                                    <table class="sheet">
                                        <thead>
                                            <tr>
                                                <th class="gutter">#</th>
                                                <th>الحي</th>
                                                <th>الصفقات</th>
                                                <th>القيمة (ر.س)</th>
                                                <th>وسيط سعر المتر</th>
                                                <th>النطاق (ر.س/م²)</th>
                                                <th>وسيط قيمة الصفقة</th>
                                                <th>التغيّر السنوي</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(row, index) in districts" :key="row.key">
                                                <tr class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                                                    :class="row.key === district && 'bg-primary-500/10'"
                                                    @click="openDistrict(row.key)">
                                                    <td class="gutter" x-text="index + 1"></td>
                                                    <td class="font-bold" x-text="row.label"></td>
                                                    <td x-text="number(row.deals)"></td>
                                                    <td x-text="money(row.value)"></td>
                                                    <td x-text="rate(row.median)"></td>
                                                    <td class="text-zinc-500" x-text="range(row)"></td>
                                                    <td x-text="row.median_price ? money(row.median_price) : '—'"></td>
                                                    <td class="font-bold" :class="changeClass(row.median, row.previous_median)"
                                                        x-text="change(row.median, row.previous_median)?.label ?? '—'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <p class="mt-4 text-center text-sm text-zinc-400" x-show="!districts.length">لا أحياء مطابقة.</p>
                            </div>
                        </section>

                        {{-- Deed by deed, for the cities whose deeds are kept --}}
                        <template x-if="deeds?.available">
                            <section x-show="view === 'deeds'" class="space-y-4">
                                <h2 class="text-lg font-extrabold" x-text="`الصفقات الفردية — ${data.city.label}`"></h2>

                                <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                    كل صفقة كما سجّلتها وزارة العدل. اختر الحي ومدى المساحة والسعر الذي يهمّك، وتتبع الأرقام والمخططات
                                    والجدول ما اخترته. يطبَّق عليها مرشّح التصنيف أعلى الصفحة، لا مرشّح فئة المساحة.
                                </p>

                                <form class="{{ $card }} flex flex-wrap items-end gap-3" @submit.prevent="filterDeeds()">
                                    <label class="flex flex-col gap-1 text-xs font-bold text-zinc-500">
                                        الحي
                                        <select x-model="deedFilters.district" @change="filterDeeds()" class="{{ $select }}">
                                            <option value="">كل الأحياء</option>
                                            <template x-for="item in deeds.districts" :key="item.key">
                                                <option :value="item.key" :selected="item.key === deedFilters.district"
                                                    x-text="`${item.label} (${number(item.deals)})`"></option>
                                            </template>
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1 text-xs font-bold text-zinc-500">
                                        المدة
                                        <select x-model.number="deedFilters.quarters" @change="filterDeeds()" class="{{ $select }}">
                                            <option value="4">آخر 4 أرباع</option>
                                            <option value="8">آخر 8 أرباع</option>
                                            <option value="12">آخر 12 ربعًا</option>
                                            <option value="0">كل ما رُفع</option>
                                        </select>
                                    </label>

                                    @foreach ([['area_min', 'المساحة من (م²)'], ['area_max', 'إلى'], ['price_min', 'السعر من (ر.س)'], ['price_max', 'إلى']] as [$field, $label])
                                        <label class="flex flex-col gap-1 text-xs font-bold text-zinc-500">
                                            {{ $label }}
                                            <input type="number" min="0" inputmode="numeric" x-model="deedFilters.{{ $field }}"
                                                class="{{ $select }} w-32" dir="ltr">
                                        </label>
                                    @endforeach

                                    <button type="submit" class="{{ $primaryButton }}" :disabled="deedsLoading"
                                        x-text="deedsLoading ? 'جارٍ القراءة…' : 'تطبيق'"></button>
                                    <button type="button" class="{{ $ghostButton }}" @click="clearDeedFilters()">مسح</button>
                                </form>

                                <p x-show="deedsError" x-cloak class="rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600" x-text="deedsError"></p>

                                <p class="text-xs text-zinc-500 dark:text-zinc-400" x-show="deeds.period.from"
                                    x-text="`من ${quarterLabel(deeds.period.from)} إلى ${quarterLabel(deeds.period.to)}.`"></p>

                                <template x-if="!deeds.summary.deals">
                                    <p class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm text-zinc-400 dark:border-zinc-700">
                                        لا صفقات بهذه الشروط.
                                    </p>
                                </template>

                                <template x-if="deeds.summary.deals">
                                    <div class="space-y-4">
                                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                            <div class="{{ $tile }}">
                                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">الصفقات</p>
                                                <p class="mt-2 text-2xl font-extrabold" x-text="number(deeds.summary.deals)"></p>
                                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-text="`بقيمة ${money(deeds.summary.value)} ر.س`"></p>
                                            </div>
                                            <div class="{{ $tile }}">
                                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">وسيط سعر المتر</p>
                                                <p class="mt-2 text-2xl font-extrabold">
                                                    <span x-text="rate(deeds.summary.median)"></span>
                                                    <span class="text-sm font-bold text-zinc-400">ر.س/م²</span>
                                                </p>
                                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-text="'النطاق ' + range(deeds.summary)"></p>
                                            </div>
                                            <div class="{{ $tile }}">
                                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">وسيط قيمة الصفقة</p>
                                                <p class="mt-2 text-2xl font-extrabold">
                                                    <span x-text="money(deeds.summary.median_price)"></span>
                                                    <span class="text-sm font-bold text-zinc-400">ر.س</span>
                                                </p>
                                            </div>
                                            <div class="{{ $tile }}">
                                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">وسيط المساحة</p>
                                                <p class="mt-2 text-2xl font-extrabold">
                                                    <span x-text="number(deeds.summary.median_area)"></span>
                                                    <span class="text-sm font-bold text-zinc-400">م²</span>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="grid gap-4 lg:grid-cols-2">
                                            <div class="{{ $card }}">
                                                <h3 class="mb-6 font-extrabold">وسيط سعر المتر شهرًا بشهر</h3>
                                                <div class="flex h-40 items-end gap-1" dir="ltr">
                                                    <template x-for="point in deeds.monthly" :key="point.key">
                                                        <div class="group relative flex h-full flex-1 items-end justify-center">
                                                            <div class="w-full rounded-t bg-primary-500/60 transition-colors group-hover:bg-primary-500"
                                                                :style="`height: ${monthHeight(point)}`"></div>
                                                            <div class="pointer-events-none absolute bottom-full z-10 mb-2 hidden whitespace-nowrap rounded-lg bg-zinc-900 px-2 py-1 text-[11px] text-white group-hover:block"
                                                                dir="rtl" x-text="`${monthLabel(point.key)} — ${rate(point.median)} ر.س/م² · ${number(point.deals)} صفقة`"></div>
                                                        </div>
                                                    </template>
                                                </div>
                                                <div class="mt-2 flex justify-between text-[11px] text-zinc-400" dir="ltr">
                                                    <span x-text="monthLabel(deeds.monthly[0]?.key)"></span>
                                                    <span x-text="monthLabel(deeds.monthly[deeds.monthly.length - 1]?.key)"></span>
                                                </div>
                                            </div>

                                            <div class="{{ $card }}">
                                                <h3 class="mb-4 font-extrabold">توزيع الصفقات حسب قيمتها</h3>
                                                <ul class="flex flex-col gap-3">
                                                    <template x-for="bracket in deeds.distribution" :key="bracket.from">
                                                        <li>
                                                            <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                                                <span class="font-medium" x-text="bracketLabel(bracket)"></span>
                                                                <span class="shrink-0 font-bold text-zinc-500" x-text="number(bracket.deals)"></span>
                                                            </div>
                                                            <div class="h-1.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800">
                                                                <div class="h-full rounded-full bg-primary-500" :style="`width: ${bracketWidth(bracket)}`"></div>
                                                            </div>
                                                        </li>
                                                    </template>
                                                </ul>
                                            </div>
                                        </div>

                                        <div class="{{ $card }}">
                                            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                                <h3 class="font-extrabold">الصفقات</h3>
                                                <div class="flex items-center gap-2 text-xs font-bold text-zinc-500">
                                                    <button type="button" class="{{ $ghostButton }} px-3 py-1" :disabled="deedFilters.page <= 1" @click="turnDeedPage(-1)">السابق</button>
                                                    <span x-text="`صفحة ${number(deeds.page)} من ${number(deeds.pages)}`"></span>
                                                    <button type="button" class="{{ $ghostButton }} px-3 py-1" :disabled="deedFilters.page >= deeds.pages" @click="turnDeedPage(1)">التالي</button>
                                                </div>
                                            </div>

                                            <div class="sheet-wrap is-inset">
                                                <table class="sheet">
                                                    <thead>
                                                        <tr>
                                                            @foreach ([['date', 'التاريخ'], [null, 'الحي'], [null, 'التصنيف'], ['area', 'المساحة (م²)'], ['price', 'السعر (ر.س)'], ['rate', 'سعر المتر']] as [$column, $label])
                                                                @if ($column)
                                                                    <th class="cursor-pointer select-none" @click="sortDeeds('{{ $column }}')">
                                                                        {{ $label }}<span x-text="sortMark('{{ $column }}')"></span>
                                                                    </th>
                                                                @else
                                                                    <th>{{ $label }}</th>
                                                                @endif
                                                            @endforeach
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-for="deed in deeds.deeds" :key="deed.id">
                                                            <tr>
                                                                <td dir="ltr" class="text-right" x-text="deed.date"></td>
                                                                <td class="font-bold" x-text="deed.district || '—'"></td>
                                                                <td x-text="deed.classification || '—'"></td>
                                                                <td x-text="number(deed.area)"></td>
                                                                <td x-text="number(deed.price)"></td>
                                                                <td class="font-bold" x-text="rate(deed.rate)"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </section>
                        </template>

                        <template x-if="view === 'deeds' && !deeds?.available">
                            <p class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm leading-relaxed text-zinc-400 dark:border-zinc-700">
                                لا صفقات فردية لهذه المدينة. الصفقات تُحفظ واحدة واحدة لجدة فقط، ومن الملفات المرفوعة بعد إضافة هذه الخاصية.
                            </p>
                        </template>

                    </div>
                </template>

                {{-- A group opened before any quarter of deeds has been uploaded --}}
                <template x-if="!hasData && ['kingdom', 'city', 'deeds'].includes(view)">
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center dark:border-zinc-700">
                        <p class="mb-4 text-sm text-zinc-400">لا بيانات لهذه المجموعة بعد: لم يُرفع أي ربع من صفقات وزارة العدل.</p>
                        <button type="button" class="{{ $primaryButton }} mx-auto" @click="go('files')">إلى الملفات والتحديث</button>
                    </div>
                </template>

                {{-- By kind of property: the authority's indicators, a second source beside the deeds --}}
                <template x-if="types">
                    <section x-show="view === 'types'" class="space-y-4">
                        <h2 class="text-lg font-extrabold" x-text="`حسب نوع العقار — ${data.city.label}`"></h2>

                        <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            المصدر: مؤشرات صفقات البيع التي تنشرها الهيئة العامة للعقار. هي متوسطات جاهزة لا وسيط، وأعدادها تختلف
                            عن صكوك وزارة العدل أعلاه، ولا يطبَّق عليها مرشّح المساحة.
                            <span x-show="typesQuarterNote" x-text="typesQuarterNote"></span>
                        </p>

                        <template x-if="!types.rows.length">
                            <p class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm leading-relaxed text-zinc-400 dark:border-zinc-700">
                                <span x-show="!types.has_data">
                                    لم تُرفع ملفات مؤشرات الهيئة بعد. نزّل ملف «مؤشرات صفقات البيع» لمنطقتك من
                                    <a :href="types.publisher_url" target="_blank" rel="noopener" class="font-bold text-primary-600 hover:underline dark:text-primary-300">صفحة الهيئة على منصة البيانات المفتوحة</a>
                                    وارفعه من الزر أعلاه.
                                </span>
                                <span x-show="types.has_data">لا أرقام من الهيئة لهذه المدينة بهذا التصنيف.</span>
                            </p>
                        </template>

                        <div class="{{ $card }}" x-show="types.rows.length">
                            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
                                <h3 class="font-extrabold" x-text="quarterLabel(types.quarter)"></h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">اضغط على نوع لعرض تاريخه وأحيائه.</p>
                            </div>

                            <div class="sheet-wrap is-inset">
                                <table class="sheet">
                                    <thead>
                                        <tr>
                                            <th>النوع</th>
                                            <th>الصفقات</th>
                                            <th>القيمة (ر.س)</th>
                                            <th>متوسط سعر المتر</th>
                                            <th>الأدنى – الأعلى (ر.س/م²)</th>
                                            <th>التغيّر السنوي</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="row in types.rows" :key="row.type">
                                            <tr class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                                                :class="row.type === type && 'bg-primary-500/10'" @click="openType(row.type)">
                                                <td class="font-bold" x-text="row.type"></td>
                                                <td x-text="number(row.deals)"></td>
                                                <td x-text="money(row.value)"></td>
                                                <td x-text="rate(row.average)"></td>
                                                <td class="text-zinc-500" x-text="extremes(row)"></td>
                                                <td class="font-bold" :class="changeClass(row.average, row.previous_average)"
                                                    x-text="change(row.average, row.previous_average)?.label ?? '—'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- The kind of property a row was opened for --}}
                        <template x-if="types.selected">
                            <div class="space-y-4 rounded-2xl border border-primary-500/30 bg-primary-500/5 p-6">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-extrabold" x-text="`${types.selected}: متوسط سعر المتر ربعًا بربع`"></h3>
                                    <button type="button" @click="openType(type)"
                                        class="text-xs font-bold text-zinc-500 hover:underline">إغلاق</button>
                                </div>

                                @include('admin.partials.market-chart', ['series' => 'types.series', 'metric' => 'average', 'current' => 'types.quarter'])

                                <p class="text-xs text-zinc-500 dark:text-zinc-400" x-show="!types.by_district">
                                    الهيئة نشرت هذا الربع على مستوى المدينة فقط، فلا تفصيل للأحياء فيه.
                                </p>

                                <div class="sheet-wrap is-inset" x-show="types.districts.length">
                                    <table class="sheet">
                                        <thead>
                                            <tr>
                                                <th class="gutter">#</th>
                                                <th>الحي</th>
                                                <th>الصفقات</th>
                                                <th>القيمة (ر.س)</th>
                                                <th>متوسط سعر المتر</th>
                                                <th>الأدنى – الأعلى (ر.س/م²)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(row, index) in types.districts" :key="row.key">
                                                <tr>
                                                    <td class="gutter" x-text="index + 1"></td>
                                                    <td class="font-bold" x-text="row.label"></td>
                                                    <td x-text="number(row.deals)"></td>
                                                    <td x-text="money(row.value)"></td>
                                                    <td x-text="rate(row.average)"></td>
                                                    <td class="text-zinc-500" x-text="extremes(row)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </section>
                </template>
            </div>
        </template>
    </div>
@endsection
