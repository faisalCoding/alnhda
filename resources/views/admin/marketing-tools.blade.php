@extends('admin.layouts.panel')

@section('title', 'أدوات التسويق')
@section('heading', 'أدوات التسويق')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $ghostButton = 'flex items-center gap-1.5 rounded-xl border border-zinc-300 px-4 py-2 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
    $primaryButton = 'flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-50';
@endphp

@section('content')
    <div x-data="marketingToolsPage()" class="space-y-6">

        <p x-show="error" x-cloak class="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-600 dark:bg-red-900/30 dark:text-red-300" x-text="error"></p>

        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Method library --}}
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-1">
                <h2 class="mb-1 font-bold">طرق التسويق</h2>
                <p class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">
                    مكتبة نصية تبني منها قوائم المهام. تعديل طريقة هنا لا يغيّر القوائم المبنية منها سابقاً.
                </p>

                <ul class="mb-4 space-y-2">
                    <template x-for="method in methods" :key="method.id">
                        <li class="group flex items-center gap-2 rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-800">
                            <template x-if="editingMethod !== method.id">
                                <span class="flex-1 cursor-text text-sm" @dblclick="startEditMethod(method)" x-text="method.title"></span>
                            </template>
                            <template x-if="editingMethod === method.id">
                                <input type="text" x-model="editingMethodTitle" @blur="saveMethod(method)"
                                    @keydown.enter="saveMethod(method)" @keydown.escape="editingMethod = null"
                                    class="flex-1 rounded-lg border border-primary-400 bg-white px-2 py-1 text-sm dark:bg-zinc-800" x-init="$nextTick(() => $el.focus())">
                            </template>
                            <button type="button" @click="startEditMethod(method)"
                                class="text-xs font-bold text-primary-600 opacity-0 transition group-hover:opacity-100 dark:text-primary-300">تعديل</button>
                            <button type="button" @click="removeMethod(method)"
                                class="text-xs font-bold text-red-500 opacity-0 transition group-hover:opacity-100">حذف</button>
                        </li>
                    </template>
                    <li x-show="!methods.length" x-cloak class="rounded-xl border border-dashed border-zinc-300 px-3 py-6 text-center text-sm text-zinc-400 dark:border-zinc-700">
                        لا توجد طرق بعد
                    </li>
                </ul>

                <form @submit.prevent="addMethod()" class="flex gap-2">
                    <input type="text" x-model="methodForm" placeholder="طريقة جديدة…" class="{{ $input }}">
                    <button type="submit" class="{{ $primaryButton }}">إضافة</button>
                </form>
            </section>

            {{-- Checklists --}}
            <section class="space-y-4 lg:col-span-2">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="openChecklistForm()" class="{{ $primaryButton }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        قائمة مهام جديدة
                    </button>
                    <span class="text-xs text-zinc-400" x-text="checklists.length + ' قائمة'"></span>
                </div>

                <div x-show="!loading && !checklists.length" x-cloak
                    class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
                    <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد قوائم مهام بعد</p>
                    <p class="mt-1 text-sm text-zinc-400">أنشئ قائمة واسحب إليها الطرق التي تريد تنفيذها</p>
                </div>

                <template x-for="checklist in checklists" :key="checklist.id">
                    <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                        <header class="flex flex-wrap items-center gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <input type="text" :value="checklist.name" @blur="renameChecklist(checklist, $event.target.value)"
                                @keydown.enter="$event.target.blur()"
                                class="min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1 font-bold hover:border-zinc-300 focus:border-primary-500 focus:outline-none dark:hover:border-zinc-700">

                            <button type="button" @click="openPicker(checklist)" class="{{ $ghostButton }}">إضافة من الطرق</button>
                            <button type="button" @click="removeChecklist(checklist)"
                                class="rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-500 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-900/30">حذف</button>
                        </header>

                        <div class="flex items-center gap-3 px-5 pt-4">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                <div class="h-full rounded-full bg-primary-500 transition-all" :style="`width: ${progress(checklist).percent}%`"></div>
                            </div>
                            <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400"
                                x-text="`${progress(checklist).done} / ${progress(checklist).total}`"></span>
                        </div>

                        <ul class="space-y-1 px-5 py-4">
                            <template x-for="item in checklist.items" :key="item.id">
                                <li class="group flex items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                    <input type="checkbox" :checked="item.is_done" @change="toggleItem(checklist, item)"
                                        class="h-4 w-4 rounded border-zinc-300 text-primary-500 focus:ring-primary-500 dark:border-zinc-600">
                                    <span class="flex-1 text-sm" :class="item.is_done && 'text-zinc-400 line-through'" x-text="item.title"></span>
                                    <button type="button" @click="removeItem(checklist, item)"
                                        class="text-xs font-bold text-red-500 opacity-0 transition group-hover:opacity-100">حذف</button>
                                </li>
                            </template>
                            <li x-show="!checklist.items.length" x-cloak class="px-2 py-3 text-sm text-zinc-400">لا توجد مهام في هذه القائمة</li>
                        </ul>

                        <footer class="border-t border-zinc-200 px-5 py-3 dark:border-zinc-800">
                            <form @submit.prevent="addItem(checklist)" class="flex gap-2">
                                <input type="text" x-model="newItem[checklist.id]" placeholder="مهمة خاصة بهذه القائمة…" class="{{ $input }}">
                                <button type="submit" class="{{ $ghostButton }}">إضافة</button>
                            </form>
                        </footer>
                    </article>
                </template>
            </section>
        </div>

        {{-- New checklist --}}
        <div x-show="showChecklistForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showChecklistForm = false" @keydown.escape.window="showChecklistForm = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-zinc-900" x-trap.noscroll="showChecklistForm">
                <h2 class="mb-5 text-lg font-extrabold">قائمة مهام جديدة</h2>

                <form @submit.prevent="createChecklist()" class="space-y-4">
                    <div>
                        <label class="{{ $label }}">اسم القائمة</label>
                        <input type="text" x-model="checklistForm.name" class="{{ $input }}" placeholder="حملة مشروع الواحة">
                        <p class="{{ $error }}" x-show="checklistErrors.name" x-text="checklistErrors.name?.[0]"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">اختر الطرق التي تريد إدراجها</label>
                        <div class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-zinc-200 p-2 dark:border-zinc-800">
                            <template x-for="method in methods" :key="method.id">
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <input type="checkbox" :value="method.id" x-model.number="checklistForm.method_ids"
                                        class="h-4 w-4 rounded border-zinc-300 text-primary-500 focus:ring-primary-500 dark:border-zinc-600">
                                    <span class="text-sm" x-text="method.title"></span>
                                </label>
                            </template>
                            <p x-show="!methods.length" x-cloak class="px-2 py-4 text-center text-sm text-zinc-400">أضف طرقاً أولاً</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showChecklistForm = false" class="{{ $ghostButton }}">إلغاء</button>
                        <button type="submit" :disabled="saving" class="{{ $primaryButton }}" x-text="saving ? 'جارٍ الإنشاء…' : 'إنشاء'"></button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Method picker --}}
        <div x-show="picker.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="picker.open = false" @keydown.escape.window="picker.open = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-zinc-900" x-trap.noscroll="picker.open">
                <h2 class="mb-1 text-lg font-extrabold">إضافة طرق إلى القائمة</h2>
                <p class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">الطرق الموجودة في القائمة أصلاً لن تتكرر.</p>

                <div class="mb-4 max-h-64 space-y-1 overflow-y-auto rounded-xl border border-zinc-200 p-2 dark:border-zinc-800">
                    <template x-for="method in methods" :key="method.id">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <input type="checkbox" :checked="picker.selected.includes(method.id)" @change="toggleSelection(method.id)"
                                class="h-4 w-4 rounded border-zinc-300 text-primary-500 focus:ring-primary-500 dark:border-zinc-600">
                            <span class="text-sm" x-text="method.title"></span>
                        </label>
                    </template>
                    <p x-show="!methods.length" x-cloak class="px-2 py-4 text-center text-sm text-zinc-400">لا توجد طرق</p>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="picker.open = false" class="{{ $ghostButton }}">إلغاء</button>
                    <button type="button" @click="confirmPicker()" :disabled="!picker.selected.length" class="{{ $primaryButton }}">إضافة</button>
                </div>
            </div>
        </div>
    </div>
@endsection
