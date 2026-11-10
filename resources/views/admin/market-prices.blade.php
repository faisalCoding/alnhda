@extends('admin.layouts.panel')

@section('title', 'أسعار السوق')
@section('heading', 'أسعار السوق')

@php
    $card = 'rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900';
    $tile = 'rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900';
    $select = 'rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800';
    $pill = 'rounded-lg px-4 py-1.5 text-sm font-bold transition-colors';
    $pillOn = 'bg-primary-500 text-white';
    $pillOff = 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800';
    $primaryButton = 'flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white hover:bg-primary-600';
    $tiles = [['deals', 'الصفقات', 'number', ''], ['value', 'قيمة الصفقات', 'money', 'ر.س'], ['median', 'وسيط سعر المتر', 'rate', 'ر.س/م²']];
    $metrics = [['median', 'سعر المتر'], ['deals', 'عدد الصفقات'], ['value', 'قيمة الصفقات']];
@endphp

@section('content')
    <div x-data="marketPricesPage()" class="space-y-6">

        {{-- Filters and upload --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3" x-show="hasData" x-cloak>
                <select x-model="quarter" @change="load()" class="{{ $select }}" aria-label="الربع">
                    <template x-for="item in quarters" :key="item.key">
                        <option :value="item.key" :selected="item.key === quarter" x-text="quarterLabel(item)"></option>
                    </template>
                </select>

                <div class="flex flex-wrap items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-800">
                    <button type="button" @click="setClassification('')" class="{{ $pill }}"
                        :class="classification === '' ? '{{ $pillOn }}' : '{{ $pillOff }}'">الكل</button>
                    <template x-for="value in classifications" :key="value">
                        <button type="button" @click="setClassification(value)" class="{{ $pill }}"
                            :class="classification === value ? '{{ $pillOn }}' : '{{ $pillOff }}'" x-text="value"></button>
                    </template>
                </div>
            </div>

            <label class="{{ $primaryButton }} mr-auto cursor-pointer" :class="uploading && 'pointer-events-none opacity-50'">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                <span x-text="uploading ? 'جارٍ الرفع…' : 'رفع ملفات الصفقات'"></span>
                <input type="file" accept=".csv,text/csv" multiple class="hidden" @change="upload($event)">
            </label>
        </div>

        <p x-show="error" x-cloak class="rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600" x-text="error"></p>
        <p x-show="loading" class="text-sm text-zinc-400">جارٍ التحميل…</p>

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

        <template x-if="!loading && data">
            <div class="space-y-6">

                {{-- Quarters the ministry has published that nobody has uploaded yet --}}
                <template x-if="waiting.length">
                    <div class="rounded-2xl border border-dashed border-amber-400 bg-amber-50 p-6 dark:bg-amber-500/10">
                        <h2 class="mb-2 font-extrabold text-amber-700 dark:text-amber-400"
                            x-text="`${number(waiting.length)} من الأرباع المنشورة لم يُرفع بعد`"></h2>
                        <p class="mb-4 text-sm leading-relaxed text-amber-700/80 dark:text-amber-300/80">
                            افتح الربع على منصة البيانات المفتوحة، نزّل ملف CSV منه، ثم ارفعه من الزر أعلاه. يمكن رفع عدة ملفات دفعة واحدة.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="item in waiting" :key="item.key">
                                <a :href="item.portal_url" target="_blank" rel="noopener"
                                    class="rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-amber-700 hover:underline dark:bg-zinc-900 dark:text-amber-300"
                                    x-text="quarterLabel(item)"></a>
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
                        <ol class="list-inside list-decimal space-y-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                            <li>افتح <a :href="data.publisher_url" target="_blank" rel="noopener" class="font-bold text-primary-600 hover:underline dark:text-primary-300">صفحة وزارة العدل على منصة البيانات المفتوحة</a> وابحث عن «الصفقات العقارية».</li>
                            <li>من كل ربع، نزّل الملف <span class="font-mono text-xs" dir="ltr">Transactions sale for real estate CSV</span>.</li>
                            <li>ارفع الملفات من زر «رفع ملفات الصفقات» أعلاه، كما هي دون فتحها في Excel.</li>
                        </ol>
                    </div>
                </template>

                <template x-if="hasData">
                    <div class="space-y-6">

                        <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            المصدر: صكوك البيع المسجلة لدى وزارة العدل. أغلبها قطع أراضٍ، فسعر المتر هنا وسيط ما دُفع في الصفقات
                            لا سعر متر الشقق والفلل المبنية.
                            <span x-show="comparedWith" x-text="`نسب التغيّر مقارنةً بـ${comparedWith}.`"></span>
                            <span x-show="!comparedWith">لا يوجد ربع مقابل من العام السابق للمقارنة.</span>
                        </p>

                        {{-- The Kingdom --}}
                        <section class="space-y-4">
                            <h2 class="text-lg font-extrabold">المملكة</h2>

                            <div class="grid gap-4 sm:grid-cols-3">
                                @foreach ($tiles as [$key, $label, $format, $unit])
                                    <div class="{{ $tile }}">
                                        <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                                        <p class="mt-2 text-3xl font-extrabold">
                                            <span x-text="{{ $format }}(data.country.current?.{{ $key }})"></span>
                                            <span class="text-sm font-bold text-zinc-400">{{ $unit }}</span>
                                        </p>
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
                        <section class="space-y-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-lg font-extrabold" x-text="data.city.label"></h2>
                                <select x-model="city" @change="setCity(city)" class="{{ $select }}" aria-label="المدينة">
                                    <template x-for="item in data.cities" :key="item.key">
                                        <option :value="item.key" :selected="item.key === city"
                                            x-text="`${item.label} (${number(item.deals)})`"></option>
                                    </template>
                                </select>
                            </div>

                            <template x-if="!data.city.current">
                                <p class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm text-zinc-400 dark:border-zinc-700">
                                    لا صفقات في هذه المدينة بهذا التصنيف خلال الربع المختار.
                                </p>
                            </template>

                            <div class="grid gap-4 sm:grid-cols-3" x-show="data.city.current">
                                @foreach ($tiles as [$key, $label, $format, $unit])
                                    <div class="{{ $tile }}">
                                        <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                                        <p class="mt-2 text-3xl font-extrabold">
                                            <span x-text="{{ $format }}(data.city.current?.{{ $key }})"></span>
                                            <span class="text-sm font-bold text-zinc-400">{{ $unit }}</span>
                                        </p>
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
                                                <th>متوسط سعر المتر</th>
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
                                                    <td class="text-zinc-500" x-text="rate(row.average)"></td>
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
                    </div>
                </template>
            </div>
        </template>
    </div>
@endsection
