@extends('admin.layouts.panel')

@section('title', 'روابط مهمة')
@section('heading', 'روابط مهمة')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $ghostButton = 'flex items-center gap-1.5 rounded-xl border border-zinc-300 px-4 py-2 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $primaryButton = 'flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-50';
@endphp

@section('content')
    <div x-data="usefulLinksPage()" class="space-y-6">

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @click="openCreate()" class="{{ $primaryButton }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                إضافة رابط
            </button>

            <input type="search" x-model="search" placeholder="بحث بالاسم أو الرابط أو الفائدة…"
                class="w-full max-w-xs rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">

            <span class="text-xs text-zinc-400" x-text="records.length + ' رابط'"></span>
        </div>

        <p x-show="error" x-cloak class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600 dark:bg-red-900/30 dark:text-red-300" x-text="error"></p>

        <div x-show="!loading && !visible.length" x-cloak
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد روابط بعد</p>
            <p class="mt-1 text-sm text-zinc-400">احفظ هنا المنصات التي تعود عليها ووصفاً لما تفيدك فيه</p>
        </div>

        <div x-show="visible.length" x-cloak class="sheet-wrap">
            <table class="sheet">
                <thead>
                    <tr>
                        <th class="gutter">#</th>
                        <th>المنصة</th>
                        <th>الرابط</th>
                        <th>الفائدة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(record, index) in visible" :key="record.id">
                        <tr>
                            <td class="gutter" x-text="index + 1"></td>
                            <td class="font-bold" x-text="record.name"></td>
                            <td class="cell-clip">
                                <a :href="record.url" target="_blank" rel="noopener noreferrer" dir="ltr"
                                    class="text-primary-600 hover:underline dark:text-primary-300" x-text="record.url"></a>
                            </td>
                            <td class="cell-clip text-zinc-600 dark:text-zinc-300" :title="record.benefit"
                                x-text="record.benefit || '—'"></td>
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

        <div x-show="showForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showForm = false" @keydown.escape.window="showForm = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-zinc-900" x-trap.noscroll="showForm">
                <h2 class="mb-5 text-lg font-extrabold" x-text="form.id ? 'تعديل الرابط' : 'رابط جديد'"></h2>

                <form @submit.prevent="save()" class="space-y-4">
                    <div>
                        <label class="{{ $label }}">اسم المنصة</label>
                        <input type="text" x-model="form.name" class="{{ $input }}">
                        <p class="{{ $error }}" x-show="formErrors.name" x-text="formErrors.name?.[0]"></p>
                    </div>
                    <div>
                        <label class="{{ $label }}">رابط المنصة</label>
                        <input type="url" x-model="form.url" class="{{ $input }}" dir="ltr" placeholder="https://">
                        <p class="{{ $error }}" x-show="formErrors.url" x-text="formErrors.url?.[0]"></p>
                    </div>
                    <div>
                        <label class="{{ $label }}">فائدة المنصة</label>
                        <textarea x-model="form.benefit" rows="3" class="{{ $input }}"
                            placeholder="فيمَ تستخدمها؟"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showForm = false" class="{{ $ghostButton }}">إلغاء</button>
                        <button type="submit" :disabled="saving" class="{{ $primaryButton }}" x-text="saving ? 'جارٍ الحفظ…' : 'حفظ'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
