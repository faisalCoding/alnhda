@extends('admin.layouts.panel')

@section('title', 'نظرة عامة')
@section('heading', 'نظرة عامة')

@section('content')
    <div x-data="overviewPage()" class="space-y-8">

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            @php
                $cards = [
                    ['key' => 'projects', 'label' => 'المشاريع', 'route' => 'projects-dashboard'],
                    ['key' => 'properties', 'label' => 'الوحدات', 'route' => 'projects-dashboard'],
                    ['key' => 'articles', 'label' => 'المقالات', 'route' => 'articles-dashboard'],
                    ['key' => 'visitors', 'label' => 'الزوار', 'route' => 'visitors-dashboard'],
                ];
            @endphp

            @foreach ($cards as $card)
                <a href="{{ route($card['route']) }}"
                    class="group rounded-2xl border border-zinc-200 bg-white p-5 transition hover:border-primary-500/40 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    <p class="mt-2 text-3xl font-extrabold tabular-nums" x-text="counts.{{ $card['key'] }} ?? 0"></p>
                </a>
            @endforeach
        </div>

        {{-- Sync center --}}
        <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <header class="flex items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <div>
                    <h2 class="font-extrabold">مركز المزامنة</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">كل الإجراءات المحفوظة محليًا وحالة وصولها إلى الخادم</p>
                </div>
                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-bold dark:bg-zinc-800"
                    x-text="ops.length + ' إجراء'"></span>
            </header>

            <div x-show="!ops.length" class="flex flex-col items-center gap-2 px-5 py-10 text-center">
                <svg class="h-10 w-10 text-primary-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p class="text-sm font-bold">كل التغييرات مُزامنة مع الخادم</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400" x-show="$store.sync.lastSyncedAt"
                    x-text="'آخر مزامنة: ' + formatTime($store.sync.lastSyncedAt)"></p>
            </div>

            <div x-show="ops.length" class="divide-y divide-zinc-100 dark:divide-zinc-800" x-cloak>
                <template x-for="op in ops" :key="op.id">
                    <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold">
                                <span x-text="opAction(op)"></span>
                                <span x-text="opEntity(op)"></span>:
                                <span class="text-zinc-500 dark:text-zinc-400" x-text="opName(op)"></span>
                            </p>
                            <p class="mt-0.5 text-xs text-red-500" x-show="opError(op)" x-text="opError(op)"></p>
                            <p class="mt-0.5 text-xs text-zinc-400" x-show="op.attempts > 0 && !opError(op)"
                                x-text="'المحاولات: ' + op.attempts"></p>
                        </div>

                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold"
                            :class="{
                                'bg-zinc-500/10 text-zinc-500 dark:text-zinc-400': op.status === 'pending',
                                'bg-sky-500/15 text-sky-600 dark:text-sky-400': op.status === 'inflight',
                                'bg-red-500/15 text-red-600 dark:text-red-400': op.status === 'failed',
                            }">
                            <span class="h-1.5 w-1.5 rounded-full"
                                :class="{
                                    'bg-zinc-400': op.status === 'pending',
                                    'bg-sky-500 sync-pulse': op.status === 'inflight',
                                    'bg-red-500': op.status === 'failed',
                                }"></span>
                            <span x-text="opStatus(op)"></span>
                        </span>

                        <div class="flex items-center gap-1.5">
                            <button type="button" x-show="op.status === 'failed'" @click="retry(op)"
                                class="rounded-lg bg-primary-500/10 px-3 py-1.5 text-xs font-bold text-primary-600 hover:bg-primary-500/20 dark:text-primary-300">
                                إعادة المحاولة
                            </button>
                            <button type="button" x-show="op.status !== 'inflight'" @click="discard(op)"
                                class="rounded-lg px-3 py-1.5 text-xs font-bold text-zinc-400 hover:bg-red-500/10 hover:text-red-500">
                                تجاهل
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        {{-- Latest items --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <header class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-800">
                    <h3 class="text-sm font-extrabold">أحدث المشاريع</h3>
                </header>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <template x-for="project in latest.projects" :key="project.id">
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="truncate text-sm font-medium" x-text="project.name"></span>
                            <span class="shrink-0 text-xs text-zinc-400" x-text="project.properties_count + ' وحدة'"></span>
                        </div>
                    </template>
                    <p x-show="!latest.projects.length" class="px-5 py-6 text-center text-xs text-zinc-400">لا توجد مشاريع بعد</p>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <header class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-800">
                    <h3 class="text-sm font-extrabold">أحدث الوحدات</h3>
                </header>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <template x-for="property in latest.properties" :key="property.id">
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="truncate text-sm font-medium" x-text="property.name"></span>
                            <span class="shrink-0 text-xs font-bold text-primary-600 dark:text-primary-300"
                                x-text="Number(property.price ?? 0).toLocaleString('ar-SA') + ' ر.س'"></span>
                        </div>
                    </template>
                    <p x-show="!latest.properties.length" class="px-5 py-6 text-center text-xs text-zinc-400">لا توجد وحدات بعد</p>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <header class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-800">
                    <h3 class="text-sm font-extrabold">أحدث المقالات</h3>
                </header>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <template x-for="article in latest.articles" :key="article.id">
                        <div class="px-5 py-3">
                            <span class="block truncate text-sm font-medium" x-text="article.title"></span>
                        </div>
                    </template>
                    <p x-show="!latest.articles.length" class="px-5 py-6 text-center text-xs text-zinc-400">لا توجد مقالات بعد</p>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <header class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-800">
                    <h3 class="text-sm font-extrabold">أحدث الزوار</h3>
                </header>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <template x-for="visitor in latest.visitors" :key="visitor.id">
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="truncate text-sm font-medium" x-text="(visitor.first_name ?? '') + ' ' + (visitor.last_name ?? '')"></span>
                            <a class="shrink-0 text-xs text-primary-600 hover:underline dark:text-primary-300" dir="ltr"
                                :href="'tel:' + visitor.phone" x-text="visitor.phone"></a>
                        </div>
                    </template>
                    <p x-show="!latest.visitors.length" class="px-5 py-6 text-center text-xs text-zinc-400">لا يوجد زوار بعد</p>
                </div>
            </section>
        </div>
    </div>
@endsection
