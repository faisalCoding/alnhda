@extends('admin.layouts.panel')

@section('title', 'الرخص الإعلانية')
@section('heading', 'الرخص الإعلانية')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $ghost = 'inline-flex items-center gap-1.5 rounded-xl border border-zinc-300 px-3.5 py-2 text-sm font-bold text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $primary = 'inline-flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-primary-600 disabled:opacity-50';
@endphp

@section('content')
    <div x-data="advertisingLicencesPage()" class="space-y-5">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" @click="openCreate()" class="{{ $primary }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                رخصة جديدة
            </button>

            <div class="relative">
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400"
                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="search" x-model="search" placeholder="بحث بالوحدة أو رقم الترخيص…"
                    class="w-64 rounded-xl border border-zinc-300 bg-white py-2 pr-9 pl-3.5 text-sm outline-none transition focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">
            </div>

            <span class="text-xs text-zinc-400" x-text="records.length + ' رخصة'"></span>

            <span x-show="needingAttention" x-cloak
                class="rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                x-text="needingAttention + ' رخصة تنتهي خلال شهر أو انتهت'"></span>
        </div>

        <p x-show="error" x-cloak
            class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600 dark:bg-red-900/30 dark:text-red-300"
            x-text="error"></p>

        {{-- Empty --}}
        <div x-show="!loading && !visible.length" x-cloak
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400"
                x-text="records.length ? 'لا نتائج مطابقة' : 'لا توجد رخص مسجّلة بعد'"></p>
            <p class="mt-1 text-sm text-zinc-400">سجّل رقم الرخصة الإعلانية لكل وحدة معروضة وتاريخ انتهائها</p>
        </div>

        {{-- Sheet --}}
        <div x-show="visible.length" x-cloak class="sheet-wrap">
            <table class="sheet">
                <thead>
                    <tr>
                        <th class="gutter">#</th>
                        <th>الوحدة</th>
                        <th>المشروع</th>
                        <th>رقم الترخيص</th>
                        <th>تاريخ الانتهاء</th>
                        <th>الحالة</th>
                        <th>ملاحظة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(record, index) in visible" :key="record.id">
                        <tr>
                            <td class="gutter" x-text="index + 1"></td>
                            <td class="font-bold">
                                <span class="flex items-center gap-1.5">
                                    <span x-text="record.unit_label"></span>
                                    <span x-show="!record.properties_id" x-cloak
                                        class="rounded-full bg-zinc-100 px-1.5 text-[10px] font-normal text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                                        title="اسم مكتوب يدوياً، غير مرتبط بوحدة على النظام">يدوي</span>
                                </span>
                            </td>
                            <td class="text-zinc-600 dark:text-zinc-300" x-text="record.project_name || '—'"></td>
                            <td class="font-bold tracking-wider" dir="ltr" x-text="record.licence_number"></td>
                            <td dir="ltr" x-text="record.expires_on ?? '—'"></td>
                            <td :class="expiryTone(record)" x-text="expiryLabel(record)"></td>
                            <td class="cell-clip text-zinc-600 dark:text-zinc-300" :title="record.note" x-text="record.note || '—'"></td>
                            <td>
                                <div class="flex gap-2">
                                    <button type="button" @click="openEdit(record)"
                                        class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">تعديل</button>
                                    <button type="button" @click="remove(record)"
                                        class="text-xs font-bold text-red-500 hover:underline">حذف</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Form --}}
        <div x-show="showForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            x-transition.opacity @click.self="showForm = false" @keydown.escape.window="showForm = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="showForm">
                <h2 class="mb-5 text-lg font-extrabold" x-text="form.id ? 'تعديل الرخصة' : 'رخصة إعلانية جديدة'"></h2>

                <form @submit.prevent="save()" class="space-y-4">
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label class="text-sm font-bold">الوحدة</label>
                            <button type="button" @click="manualUnit = !manualUnit"
                                class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300"
                                x-text="manualUnit ? 'اختر من الوحدات' : 'اكتب يدوياً'"></button>
                        </div>

                        <select x-show="!manualUnit" x-model.number="form.properties_id" class="{{ $input }}">
                            <option value="">اختر وحدة…</option>
                            <template x-for="unit in units" :key="unit.id">
                                <option :value="unit.id" x-text="unit.name"></option>
                            </template>
                        </select>

                        <input x-show="manualUnit" x-cloak type="text" x-model="form.unit_name" class="{{ $input }}"
                            placeholder="اسم الوحدة كما تريد تسجيله">

                        <p class="mt-1 text-xs text-zinc-400" x-show="!manualUnit && !units.length">
                            لا توجد وحدات على النظام. اكتب الاسم يدوياً.
                        </p>
                        <p class="{{ $error }}" x-show="formErrors.unit_name" x-text="formErrors.unit_name?.[0]"></p>
                        <p class="{{ $error }}" x-show="formErrors.properties_id" x-text="formErrors.properties_id?.[0]"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">رقم الترخيص</label>
                        <input type="text" x-model="form.licence_number" class="{{ $input }}" dir="ltr" placeholder="7200000000">
                        <p class="{{ $error }}" x-show="formErrors.licence_number" x-text="formErrors.licence_number?.[0]"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">تاريخ الانتهاء</label>
                        <input type="date" x-model="form.expires_on" class="{{ $input }}" dir="ltr">
                        <p class="{{ $error }}" x-show="formErrors.expires_on" x-text="formErrors.expires_on?.[0]"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">ملاحظة</label>
                        <textarea x-model="form.note" rows="2" class="{{ $input }}"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showForm = false" class="{{ $ghost }}">إلغاء</button>
                        <button type="submit" :disabled="saving" class="{{ $primary }}"
                            x-text="saving ? 'جارٍ الحفظ…' : 'حفظ'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
