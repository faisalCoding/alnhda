@extends('admin.layouts.panel')

@section('title', 'حركة السير')
@section('heading', 'حركة السير')

@php
    $card = 'rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900';
    $tile = 'rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900';
@endphp

@section('content')
    <div x-data="trafficPage()" class="space-y-6">

        {{-- Range --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-800">
                <template x-for="range in (data?.ranges ?? [7, 30, 90])" :key="range">
                    <button type="button" @click="setRange(range)"
                        class="rounded-lg px-4 py-1.5 text-sm font-bold transition-colors"
                        :class="days === range ? 'bg-primary-500 text-white' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                        x-text="`آخر ${range} يومًا`"></button>
                </template>
            </div>

            <p class="text-xs text-zinc-400" x-show="lastUpdated" x-cloak
                x-text="'آخر تحديث: ' + lastUpdated"></p>
        </div>

        <p x-show="error" x-cloak class="rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600" x-text="error"></p>
        <p x-show="loading" class="text-sm text-zinc-400">جارٍ التحميل…</p>

        <template x-if="!loading && data">
            <div class="space-y-6">

                {{-- Not connected yet --}}
                <template x-if="!data.google.configured">
                    <div class="rounded-2xl border border-dashed border-amber-400 bg-amber-50 p-6 dark:bg-amber-500/10">
                        <h2 class="mb-2 font-extrabold text-amber-700 dark:text-amber-400">تحليلات جوجل غير موصولة بعد</h2>
                        <p class="mb-4 text-sm text-amber-700/80 dark:text-amber-300/80" x-text="data.google.problem"></p>
                        <ol class="list-inside list-decimal space-y-1.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                            <li>أنشئ حساب خدمة في Google Cloud وفعّل واجهة <span class="font-mono text-xs">Google Analytics Data API</span>.</li>
                            <li>امنح بريد حساب الخدمة صلاحية «قارئ» على خاصية GA4.</li>
                            <li>ارفع ملف المفتاح إلى الخادم خارج مجلد المشروع، وضع مساره في <span class="font-mono text-xs">GA4_CREDENTIALS_PATH</span>.</li>
                            <li>ضع رقم الخاصية في <span class="font-mono text-xs">GA4_PROPERTY_ID</span> ثم أعد <span class="font-mono text-xs">config:cache</span>.</li>
                        </ol>
                        <p class="mt-4 text-xs text-zinc-500">
                            سجلات الخادم أدناه تعمل بلا هذا الإعداد — وتظهر لك الزواحف وأخطاء الصفحات فورًا.
                        </p>
                    </div>
                </template>

                {{-- Headline numbers --}}
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([['users', 'الزوار'], ['sessions', 'الجلسات'], ['views', 'مشاهدات الصفحات']] as [$key, $label])
                        <div class="{{ $tile }}">
                            <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                            <p class="mt-2 text-3xl font-extrabold" x-text="number(data.google.totals.{{ $key }})"></p>
                            <template x-if="change(data.google.totals.{{ $key }}, data.google.previous_totals.{{ $key }})">
                                <p class="mt-1 text-xs font-bold"
                                    :class="{
                                        'text-primary-600 dark:text-primary-300': change(data.google.totals.{{ $key }}, data.google.previous_totals.{{ $key }}).direction === 'up',
                                        'text-red-500': change(data.google.totals.{{ $key }}, data.google.previous_totals.{{ $key }}).direction === 'down',
                                        'text-zinc-400': ['flat', 'new'].includes(change(data.google.totals.{{ $key }}, data.google.previous_totals.{{ $key }}).direction),
                                    }"
                                    x-text="change(data.google.totals.{{ $key }}, data.google.previous_totals.{{ $key }}).label + ' عن الفترة السابقة'"></p>
                            </template>
                        </div>
                    @endforeach

                    <div class="{{ $tile }}">
                        <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">طلبات الزواحف</p>
                        <p class="mt-2 text-3xl font-extrabold" x-text="number(data.server.totals.bot_requests)"></p>
                        <p class="mt-1 text-xs text-zinc-400">من سجل الخادم — لا تراها تحليلات جوجل</p>
                    </div>
                </div>

                {{-- Timeline --}}
                <section class="{{ $card }}">
                    <h2 class="mb-1 font-extrabold">الزوار يومًا بيوم</h2>
                    <p class="mb-6 text-xs text-zinc-500 dark:text-zinc-400">
                        العمود الأخضر زوار جوجل، والرمادي خلفه إجمالي طلبات الخادم بما فيها الزواحف.
                    </p>

                    <div class="flex h-48 items-end gap-1" dir="ltr">
                        <template x-for="day in timeline" :key="day.date">
                            <div class="group relative flex h-full flex-1 items-end justify-center">
                                <div class="w-full rounded-t bg-zinc-200 dark:bg-zinc-700" :style="`height: ${barHeight(day.requests)}`"></div>
                                <div class="absolute bottom-0 w-full rounded-t bg-primary-500" :style="`height: ${barHeight(day.users)}`"></div>

                                <div class="pointer-events-none absolute bottom-full mb-2 hidden whitespace-nowrap rounded-lg bg-zinc-900 px-2 py-1 text-[11px] text-white group-hover:block"
                                    dir="rtl"
                                    x-text="`${shortDate(day.date)} — ${number(day.users)} زائر · ${number(day.requests)} طلب`"></div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-2 flex justify-between text-[11px] text-zinc-400" dir="ltr">
                        <span x-text="timeline.length ? shortDate(timeline[0].date) : ''"></span>
                        <span x-text="timeline.length ? shortDate(timeline[timeline.length - 1].date) : ''"></span>
                    </div>
                </section>

                {{-- Breakdowns --}}
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ([['top_pages', 'أكثر الصفحات قراءة', 'google'], ['channels', 'من أين جاء الزوار', 'google'], ['cities', 'المدن', 'google'], ['top_bots', 'الزواحف', 'server'], ['not_found', 'صفحات مفقودة (404)', 'server']] as [$key, $label, $source])
                        <section class="{{ $card }}" x-show="data.{{ $source }}.{{ $key }}?.length" x-cloak>
                            <h2 class="mb-4 font-extrabold">{{ $label }}</h2>
                            <ul class="flex flex-col gap-3">
                                <template x-for="entry in data.{{ $source }}.{{ $key }}" :key="entry.label">
                                    <li>
                                        <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                            <span class="truncate font-medium" x-text="entry.label"></span>
                                            <span class="shrink-0 font-bold text-zinc-500" x-text="number(entry.value)"></span>
                                        </div>
                                        <div class="h-1.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800">
                                            <div class="h-full rounded-full bg-primary-500"
                                                :style="`width: ${share(entry, data.{{ $source }}.{{ $key }})}`"></div>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </section>
                    @endforeach
                </div>

                {{-- Server --}}
                <section class="{{ $card }}" x-show="data.server.has_data" x-cloak>
                    <h2 class="mb-1 font-extrabold">الخادم</h2>
                    <p class="mb-5 text-xs text-zinc-500 dark:text-zinc-400">من سجل أباتشي — يشمل من يمنع التتبّع ومن لا يشغّل جافاسكربت.</p>

                    <div class="grid gap-4 sm:grid-cols-4">
                        <div>
                            <p class="text-xs font-bold text-zinc-500">إجمالي الطلبات</p>
                            <p class="mt-1 text-2xl font-extrabold" x-text="number(data.server.totals.requests)"></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-zinc-500">طلبات بشرية</p>
                            <p class="mt-1 text-2xl font-extrabold" x-text="number(data.server.totals.human_requests)"></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-zinc-500">النطاق المستهلك</p>
                            <p class="mt-1 text-2xl font-extrabold" x-text="bytes(data.server.totals.bytes)"></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-zinc-500">أخطاء</p>
                            <p class="mt-1 text-2xl font-extrabold" x-text="number(data.server.totals.errors)"></p>
                        </div>
                    </div>
                </section>

                <template x-if="!data.google.has_data && !data.server.has_data">
                    <p class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center text-sm text-zinc-400 dark:border-zinc-700">
                        لا توجد بيانات بعد. تُجمع الأرقام تلقائيًا كل ليلة، فانتظر أول تشغيل بعد منتصف الليل.
                    </p>
                </template>
            </div>
        </template>
    </div>
@endsection
