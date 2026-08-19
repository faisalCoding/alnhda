@extends('admin.layouts.panel')

@section('title', 'اشتراكات البرامج')
@section('heading', 'اشتراكات البرامج')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $ghostButton = 'flex items-center gap-1.5 rounded-xl border border-zinc-300 px-4 py-2 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $primaryButton = 'flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-50';
@endphp

@section('content')
    <div x-data="subscriptionsPage()" class="space-y-6">

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @click="openCreate()" class="{{ $primaryButton }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                إضافة اشتراك
            </button>

            <input type="search" x-model="search" placeholder="بحث بالاسم أو الحساب…"
                class="w-full max-w-xs rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">

            <span class="text-xs text-zinc-400" x-text="records.length + ' اشتراك'"></span>

            <span x-show="!pinIsSet" x-cloak
                class="mr-auto rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                عيّن رمز الإظهار من صفحة حسابات التواصل
            </span>
        </div>

        <p x-show="error" x-cloak class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600 dark:bg-red-900/30 dark:text-red-300" x-text="error"></p>

        <div x-show="!loading && !visible.length" x-cloak
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد اشتراكات بعد</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="record in visible" :key="record.id">
                <article class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate font-bold" x-text="record.name"></h2>
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="copyIdentifier(record)"
                                    class="group/copy flex min-w-0 items-center gap-1.5 rounded-lg px-1.5 py-0.5 -mr-1.5 text-sm text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                    :title="'نسخ ' + record.identifier">
                                    <span class="truncate" dir="ltr" x-text="record.identifier"></span>
                                    <span x-show="copied === 'id-' + record.id" x-cloak
                                        class="shrink-0 text-xs font-bold text-emerald-600 dark:text-emerald-400">تم النسخ</span>
                                </button>

                                <template x-if="record.url">
                                    <a :href="record.url" target="_blank" rel="noopener noreferrer"
                                        class="shrink-0 rounded-lg p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-primary-600 dark:hover:bg-zinc-800 dark:hover:text-primary-300"
                                        :title="'فتح ' + record.name">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </a>
                                </template>
                            </div>
                        </div>
                        <span class="shrink-0 text-xs" :class="expiryTone(record)" x-text="expiryLabel(record)"></span>
                    </div>

                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">تاريخ الانتهاء</dt>
                            <dd dir="ltr" x-text="record.expires_on ?? '—'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">حساب الدفع</dt>
                            <dd>
                                <template x-if="record.has_payment_account && !revealed[record.id]">
                                    <button type="button" @click="askForPin(record)"
                                        class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">إظهار</button>
                                </template>
                                <template x-if="revealed[record.id]">
                                    <span class="flex items-center gap-2">
                                        <code class="text-xs font-bold" dir="ltr" x-text="revealed[record.id]"></code>
                                        <button type="button" @click="hide(record.id)"
                                            class="text-xs font-bold text-zinc-500 hover:underline">إخفاء</button>
                                    </span>
                                </template>
                                <span x-show="!record.has_payment_account" x-cloak class="text-xs text-zinc-400">—</span>
                            </dd>
                        </div>
                    </dl>

                    <p x-show="record.note" x-cloak
                        class="rounded-xl bg-zinc-50 px-3 py-2 text-xs leading-relaxed text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                        x-text="record.note"></p>

                    <div class="mt-auto flex gap-2 pt-2">
                        <button type="button" @click="openEdit(record)" class="{{ $ghostButton }} flex-1 justify-center">تعديل</button>
                        <button type="button" @click="remove(record)"
                            class="rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-500 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-900/30">حذف</button>
                    </div>
                </article>
            </template>
        </div>

        {{-- Form --}}
        <div x-show="showForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showForm = false" @keydown.escape.window="showForm = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-zinc-900" x-trap.noscroll="showForm">
                <h2 class="mb-5 text-lg font-extrabold" x-text="form.id ? 'تعديل الاشتراك' : 'اشتراك جديد'"></h2>

                <form @submit.prevent="save()" class="space-y-4">
                    <div>
                        <label class="{{ $label }}">اسم المنصة</label>
                        <input type="text" x-model="form.name" class="{{ $input }}" placeholder="Canva Pro">
                        <p class="{{ $error }}" x-show="formErrors.name" x-text="formErrors.name?.[0]"></p>
                    </div>
                    <div>
                        <label class="{{ $label }}">البريد أو رقم الهاتف أو اسم المستخدم</label>
                        <input type="text" x-model="form.identifier" class="{{ $input }}" dir="ltr">
                        <p class="{{ $error }}" x-show="formErrors.identifier" x-text="formErrors.identifier?.[0]"></p>
                    </div>
                    <div>
                        <label class="{{ $label }}">رابط المنصة</label>
                        <input type="url" x-model="form.url" class="{{ $input }}" dir="ltr" placeholder="https://">
                        <p class="{{ $error }}" x-show="formErrors.url" x-text="formErrors.url?.[0]"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">تاريخ انتهاء الاشتراك</label>
                        <input type="date" x-model="form.expires_on" class="{{ $input }}" dir="ltr">
                        <p class="{{ $error }}" x-show="formErrors.expires_on" x-text="formErrors.expires_on?.[0]"></p>
                    </div>
                    <div>
                        <label class="{{ $label }}">رقم حساب الدفع</label>
                        <input type="text" x-model="form.payment_account" class="{{ $input }}" dir="ltr"
                            placeholder="مثال: بطاقة الشركة (فيزا)">
                        <p class="mt-1 text-xs text-zinc-400">يُخزَّن مشفّراً ولا يظهر إلا برمز الإظهار. اكتب ما يعرّف الحساب لا رقم البطاقة كاملاً.</p>
                    </div>
                    <div>
                        <label class="{{ $label }}">ملاحظة</label>
                        <textarea x-model="form.note" rows="3" class="{{ $input }}"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showForm = false" class="{{ $ghostButton }}">إلغاء</button>
                        <button type="submit" :disabled="saving" class="{{ $primaryButton }}" x-text="saving ? 'جارٍ الحفظ…' : 'حفظ'"></button>
                    </div>
                </form>
            </div>
        </div>

        {{-- PIN prompt --}}
        <div x-show="pinPrompt.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="pinPrompt.open = false" @keydown.escape.window="pinPrompt.open = false">
            <div class="w-full max-w-xs rounded-2xl bg-white p-6 text-center dark:bg-zinc-900" x-trap.noscroll="pinPrompt.open">
                <h2 class="mb-1 text-lg font-extrabold">رمز الإظهار</h2>
                <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">أدخل الرمز المكوّن من أربعة أرقام</p>

                <form @submit.prevent="submitPin()">
                    <input type="password" inputmode="numeric" maxlength="4" x-model="pinPrompt.value" dir="ltr"
                        class="{{ $input }} text-center text-2xl tracking-[0.5em]" autocomplete="off">
                    <p class="{{ $error }}" x-show="pinPrompt.error" x-text="pinPrompt.error"></p>

                    <div class="mt-5 flex justify-center gap-2">
                        <button type="button" @click="pinPrompt.open = false" class="{{ $ghostButton }}">إلغاء</button>
                        <button type="submit" :disabled="pinPrompt.busy || pinPrompt.value.length !== 4"
                            class="{{ $primaryButton }}" x-text="pinPrompt.busy ? '…' : 'إظهار'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
