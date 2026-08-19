@extends('admin.layouts.panel')

@section('title', 'حسابات التواصل')
@section('heading', 'حسابات التواصل')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $ghostButton = 'flex items-center gap-1.5 rounded-xl border border-zinc-300 px-4 py-2 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $primaryButton = 'flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-50';
@endphp

@section('content')
    <div x-data="socialAccountsPage()" class="space-y-6">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @click="openCreate()" class="{{ $primaryButton }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                إضافة منصة
            </button>

            <button type="button" @click="showTemplates = !showTemplates" class="{{ $ghostButton }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                </svg>
                قائمة المهام النموذجية
                <span class="rounded-full bg-zinc-200 px-1.5 text-xs dark:bg-zinc-700" x-text="templates.length"></span>
            </button>

            <span class="text-xs text-zinc-400" x-text="platforms.length + ' منصة'"></span>

            <span x-show="!pinIsSet" x-cloak
                class="mr-auto rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                لم تعيّن رمز الإظهار بعد
            </span>
        </div>

        <p x-show="error" x-cloak class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600 dark:bg-red-900/30 dark:text-red-300"
            x-text="error"></p>

        {{-- Task template list --}}
        <section x-show="showTemplates" x-cloak x-collapse
            class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="mb-1 font-bold">قائمة المهام النموذجية</h2>
            <p class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">
                تُنسخ هذه المهام تلقائياً إلى كل منصة جديدة. تعديلها هنا لا يغيّر المنصات القائمة.
            </p>

            <ul class="mb-4 space-y-2">
                <template x-for="template in templates" :key="template.id">
                    <li class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-2.5 dark:border-zinc-800">
                        <span class="flex-1 text-sm" x-text="template.title"></span>
                        <button type="button" @click="deleteTemplate(template)"
                            class="text-xs font-bold text-red-500 hover:underline">حذف</button>
                    </li>
                </template>
                <li x-show="!templates.length" class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-400 dark:border-zinc-700">
                    لا توجد مهام نموذجية بعد
                </li>
            </ul>

            <form @submit.prevent="addTemplate()" class="flex gap-2">
                <input type="text" x-model="templateForm" placeholder="مهمة جديدة، مثل: تفعيل التحقق بخطوتين"
                    class="{{ $input }}">
                <button type="submit" class="{{ $primaryButton }}">إضافة</button>
            </form>
        </section>

        {{-- Empty state --}}
        <div x-show="!loading && !platforms.length" x-cloak
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد منصات بعد</p>
            <p class="mt-1 text-sm text-zinc-400">أضف منصة لتبدأ متابعة مهامها</p>
        </div>

        {{-- Platforms --}}
        <template x-for="platform in platforms" :key="platform.id">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">

                <header class="flex flex-wrap items-center gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate font-bold" x-text="platform.name"></h2>
                        <p class="truncate text-sm text-zinc-500 dark:text-zinc-400" dir="ltr" x-text="platform.identifier"></p>
                    </div>

                    {{-- Password --}}
                    <div class="flex items-center gap-2">
                        <template x-if="platform.has_password && !revealed[platform.id]">
                            <button type="button" @click="askForPin(platform)" class="{{ $ghostButton }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                إظهار كلمة المرور
                            </button>
                        </template>

                        <template x-if="revealed[platform.id]">
                            <div class="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 dark:border-amber-700 dark:bg-amber-900/30">
                                <code class="text-sm font-bold" dir="ltr" x-text="revealed[platform.id]"></code>
                                <button type="button" @click="copy(platform.id)"
                                    class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">نسخ</button>
                                <button type="button" @click="hide(platform.id)"
                                    class="text-xs font-bold text-zinc-500 hover:underline">إخفاء</button>
                            </div>
                        </template>

                        <span x-show="!platform.has_password" x-cloak class="text-xs text-zinc-400">بلا كلمة مرور</span>

                        <button type="button" @click="openEdit(platform)" class="{{ $ghostButton }}">تعديل</button>
                        <button type="button" @click="deletePlatform(platform)"
                            class="rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-500 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-900/30">حذف</button>
                    </div>
                </header>

                {{-- Progress --}}
                <div class="flex items-center gap-3 px-5 pt-4">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-primary-500 transition-all"
                            :style="`width: ${progress(platform).percent}%`"></div>
                    </div>
                    <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400"
                        x-text="`${progress(platform).done} / ${progress(platform).total}`"></span>
                </div>

                {{-- Tasks --}}
                <ul class="space-y-1 px-5 py-4">
                    <template x-for="task in platform.tasks" :key="task.id">
                        <li class="group flex items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                            <input type="checkbox" :checked="task.is_done" @change="toggleTask(platform, task)"
                                class="h-4 w-4 rounded border-zinc-300 text-primary-500 focus:ring-primary-500 dark:border-zinc-600">
                            <span class="flex-1 text-sm" :class="task.is_done && 'text-zinc-400 line-through'" x-text="task.title"></span>
                            <button type="button" @click="deleteTask(platform, task)"
                                class="text-xs font-bold text-red-500 opacity-0 transition group-hover:opacity-100">حذف</button>
                        </li>
                    </template>

                    <li x-show="!platform.tasks.length" x-cloak class="px-2 py-3 text-sm text-zinc-400">
                        لا توجد مهام لهذه المنصة
                    </li>
                </ul>

                <footer class="flex flex-wrap gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-800">
                    <form @submit.prevent="addTask(platform)" class="flex flex-1 gap-2">
                        <input type="text" x-model="newTask[platform.id]" placeholder="مهمة خاصة بهذه المنصة…"
                            class="{{ $input }}">
                        <button type="submit" class="{{ $ghostButton }}">إضافة</button>
                    </form>
                    <button type="button" @click="applyTemplates(platform)" class="{{ $ghostButton }}">
                        استيراد المهام النموذجية
                    </button>
                </footer>
            </section>
        </template>

        {{-- Platform form --}}
        <div x-show="showForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showForm = false" @keydown.escape.window="showForm = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-zinc-900" x-trap.noscroll="showForm">
                <h2 class="mb-5 text-lg font-extrabold" x-text="form.id ? 'تعديل المنصة' : 'منصة جديدة'"></h2>

                <form @submit.prevent="savePlatform()" class="space-y-4">
                    <div>
                        <label class="{{ $label }}">اسم المنصة</label>
                        <input type="text" x-model="form.name" class="{{ $input }}" placeholder="إنستغرام">
                        <p class="{{ $error }}" x-show="formErrors.name" x-text="formErrors.name?.[0]"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">اسم المستخدم أو البريد أو رقم الهاتف</label>
                        <input type="text" x-model="form.identifier" class="{{ $input }}" dir="ltr">
                        <p class="{{ $error }}" x-show="formErrors.identifier" x-text="formErrors.identifier?.[0]"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">كلمة المرور</label>
                        <input type="password" x-model="form.password" class="{{ $input }}" dir="ltr" autocomplete="new-password">
                        <p class="mt-1 text-xs text-zinc-400" x-show="form.id">اتركها فارغة للإبقاء على كلمة المرور الحالية</p>
                        <p class="{{ $error }}" x-show="formErrors.password" x-text="formErrors.password?.[0]"></p>
                    </div>

                    <p class="rounded-xl bg-zinc-100 px-3 py-2 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                        x-show="!form.id">
                        ستُنسخ قائمة المهام النموذجية إلى هذه المنصة تلقائياً.
                    </p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showForm = false" class="{{ $ghostButton }}">إلغاء</button>
                        <button type="submit" :disabled="saving" class="{{ $primaryButton }}"
                            x-text="saving ? 'جارٍ الحفظ…' : 'حفظ'"></button>
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
                        class="{{ $input }} text-center text-2xl tracking-[0.5em]" autocomplete="off" x-ref="pinInput"
                        x-effect="pinPrompt.open && $nextTick(() => $refs.pinInput.focus())">
                    <p class="{{ $error }}" x-show="pinPrompt.error" x-text="pinPrompt.error"></p>

                    <div class="mt-5 flex justify-center gap-2">
                        <button type="button" @click="pinPrompt.open = false" class="{{ $ghostButton }}">إلغاء</button>
                        <button type="submit" :disabled="pinPrompt.busy || pinPrompt.value.length !== 4"
                            class="{{ $primaryButton }}" x-text="pinPrompt.busy ? '…' : 'إظهار'"></button>
                    </div>
                </form>
            </div>
        </div>

        {{-- PIN setup --}}
        <div x-show="pinSetup.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="pinSetup.open = false" @keydown.escape.window="pinSetup.open = false">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 dark:bg-zinc-900" x-trap.noscroll="pinSetup.open">
                <h2 class="mb-1 text-lg font-extrabold">تعيين رمز الإظهار</h2>
                <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">
                    رمز من أربعة أرقام يُطلب منك عند كل عملية إظهار لكلمة مرور.
                </p>

                <form @submit.prevent="saveRevealPin()" class="space-y-4">
                    <div>
                        <label class="{{ $label }}">الرمز</label>
                        <input type="password" inputmode="numeric" maxlength="4" x-model="pinSetup.pin" dir="ltr"
                            class="{{ $input }} text-center text-xl tracking-[0.4em]" autocomplete="off">
                    </div>
                    <div>
                        <label class="{{ $label }}">تأكيد الرمز</label>
                        <input type="password" inputmode="numeric" maxlength="4" x-model="pinSetup.pin_confirmation" dir="ltr"
                            class="{{ $input }} text-center text-xl tracking-[0.4em]" autocomplete="off">
                    </div>
                    <div>
                        <label class="{{ $label }}">كلمة مرور حسابك</label>
                        <input type="password" x-model="pinSetup.current_password" class="{{ $input }}" dir="ltr"
                            autocomplete="current-password">
                    </div>

                    <p class="{{ $error }}" x-show="pinSetup.error" x-text="pinSetup.error"></p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="pinSetup.open = false" class="{{ $ghostButton }}">إلغاء</button>
                        <button type="submit" :disabled="pinSetup.busy" class="{{ $primaryButton }}"
                            x-text="pinSetup.busy ? 'جارٍ الحفظ…' : 'حفظ الرمز'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
