@extends('admin.layouts.panel')

@section('title', 'العملاء المحتملون')
@section('heading', 'العملاء المحتملون')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
    $ghostButton = 'flex items-center gap-1.5 rounded-xl border border-zinc-300 px-4 py-2 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';
@endphp

@section('content')
    <div x-data="leadsPage()" class="space-y-6">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3">
            <input type="search" x-model="search" placeholder="بحث بالاسم أو الهاتف أو العقار…"
                class="w-full max-w-xs rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">

            <select x-model="classification"
                class="rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">
                <option value="">كل التصنيفات</option>
                <template x-for="value in classifications" :key="value">
                    <option :value="value" x-text="value"></option>
                </template>
            </select>

            <span class="text-xs text-zinc-400" x-text="leads.length + ' عميل'"></span>

            <div class="mr-auto flex flex-wrap items-center gap-2">
                <label class="{{ $ghostButton }} cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span x-text="importing ? 'جارٍ القراءة…' : 'استيراد CSV'"></span>
                    <input type="file" accept=".csv,text/csv" class="hidden" @change="previewImport($event)">
                </label>

                <button type="button" class="{{ $ghostButton }}" @click="exportCsv()">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    تصدير CSV
                </button>

                <button type="button" @click="openBulkSend()" :disabled="!$store.sync.online"
                    class="flex items-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-bold text-emerald-700 hover:bg-emerald-500/20 disabled:opacity-50 dark:text-emerald-400">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.87 9.87 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.25-8.23 2.2 0 4.27.86 5.83 2.42a8.19 8.19 0 0 1 2.41 5.83c0 4.54-3.69 8.22-8.24 8.22Z" />
                    </svg>
                    إرسال واتساب للقائمة
                </button>

                <button type="button" @click="openCreate()"
                    class="flex items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-primary-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    عميل جديد
                </button>
            </div>
        </div>

        {{-- Empty state --}}
        <div x-show="!leads.length"
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <p class="font-bold text-zinc-500 dark:text-zinc-400">لا يوجد عملاء محتملون بعد</p>
            <p class="mt-1 text-sm text-zinc-400">أضف عميلًا يدويًا أو استورد ملف CSV — يُحفظ محليًا ويُزامن تلقائيًا</p>
            <button type="button" @click="downloadTemplate()"
                class="mt-4 text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">
                تنزيل نموذج CSV جاهز
            </button>
        </div>

        {{-- Leads table --}}
        <div x-show="leads.length" x-cloak
            class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-right text-sm">
                    <thead>
                        <tr class="border-b border-zinc-100 text-xs text-zinc-400 dark:border-zinc-800">
                            <th class="px-5 py-3 font-bold">الاسم</th>
                            <th class="px-5 py-3 font-bold">رقم الهاتف</th>
                            <th class="px-5 py-3 font-bold">العقار</th>
                            <th class="px-5 py-3 font-bold">التاريخ</th>
                            <th class="px-5 py-3 font-bold">التصنيف</th>
                            <th class="px-5 py-3 font-bold">المزامنة</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <template x-for="lead in leads" :key="lead.id">
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-5 py-3 font-bold" x-text="lead.name"></td>
                                <td class="px-5 py-3">
                                    <a :href="'tel:' + lead.phone" dir="ltr"
                                        class="font-medium text-primary-600 hover:underline dark:text-primary-300"
                                        x-text="lead.phone"></a>
                                </td>
                                <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300" x-text="lead.property || '—'"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-xs text-zinc-500 dark:text-zinc-400"
                                    x-text="formatDate(lead.lead_date)"></td>
                                <td class="px-5 py-3">
                                    <span x-show="lead.classification"
                                        class="rounded-full bg-primary-500/10 px-2.5 py-1 text-xs font-bold text-primary-600 dark:text-primary-300"
                                        x-text="lead.classification"></span>
                                    <span x-show="!lead.classification" class="text-zinc-400">—</span>
                                </td>
                                <td class="px-5 py-3">
                                    @include('admin.partials.sync-badge', ['record' => 'lead'])
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-left">
                                    <button type="button" @click="openSend(lead)" :disabled="!canSendTo(lead)"
                                        :title="canSendTo(lead) ? 'إرسال رسالة واتساب' : 'يتطلب اتصالًا واكتمال المزامنة'"
                                        class="rounded-lg px-3 py-1.5 text-xs font-bold text-emerald-600 hover:bg-emerald-500/10 disabled:opacity-40 dark:text-emerald-400">
                                        واتساب
                                    </button>
                                    <button type="button" @click="openEdit(lead)"
                                        class="rounded-lg px-3 py-1.5 text-xs font-bold hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                        تعديل
                                    </button>
                                    <button type="button" @click="remove(lead)"
                                        class="rounded-lg px-3 py-1.5 text-xs font-bold text-red-500 hover:bg-red-500/10">
                                        حذف
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Import preview --}}
        <div x-show="importPreview" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="cancelImport()"></div>

            <div class="relative flex max-h-full w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900"
                @keydown.escape.window="cancelImport()">
                <header class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-extrabold">استيراد عملاء من ملف CSV</h2>
                    <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400" x-text="importPreview?.fileName"></p>
                </header>

                <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5 text-sm">
                    <template x-if="importPreview?.error">
                        <div class="rounded-xl bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600 dark:text-red-400"
                            x-text="importPreview.error"></div>
                    </template>

                    <template x-if="!importPreview?.error">
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-primary-500/10 px-4 py-3">
                                    <p class="text-xs text-primary-700 dark:text-primary-300">صفوف ستُضاف</p>
                                    <p class="text-2xl font-extrabold tabular-nums text-primary-600 dark:text-primary-300"
                                        x-text="importPreview.rows.length"></p>
                                </div>
                                <div class="rounded-xl bg-zinc-100 px-4 py-3 dark:bg-zinc-800">
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">صفوف متجاهلة</p>
                                    <p class="text-2xl font-extrabold tabular-nums" x-text="importPreview.skipped"></p>
                                </div>
                            </div>

                            <p x-show="importPreview.skipped" class="text-xs text-zinc-500 dark:text-zinc-400">
                                الصفوف المتجاهلة ينقصها الاسم أو رقم الهاتف.
                            </p>

                            <p x-show="importPreview.truncated" class="text-xs font-bold text-amber-600 dark:text-amber-400"
                                x-text="'تم الاقتصار على أول 2000 صف — ' + importPreview.truncated + ' صف إضافي لن يُستورد.'"></p>

                            <div>
                                <p class="mb-1.5 text-xs font-bold text-zinc-500 dark:text-zinc-400">الأعمدة المتعرَّف عليها</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="column in importPreview.columns" :key="column">
                                        <span class="rounded-lg bg-zinc-100 px-2 py-1 text-xs font-bold dark:bg-zinc-800"
                                            x-text="column"></span>
                                    </template>
                                </div>
                            </div>

                            <div x-show="importPreview.sample.length">
                                <p class="mb-1.5 text-xs font-bold text-zinc-500 dark:text-zinc-400">أول الصفوف</p>
                                <ul class="space-y-1">
                                    <template x-for="row in importPreview.sample" :key="row.phone + row.name">
                                        <li class="rounded-lg bg-zinc-50 px-3 py-2 text-xs dark:bg-zinc-800/60">
                                            <span class="font-bold" x-text="row.name"></span>
                                            <span class="text-zinc-500" dir="ltr" x-text="' — ' + row.phone"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                تُضاف الصفوف إلى الطابور المحلي وتُزامن مع الخادم تلقائيًا — يعمل الاستيراد حتى بدون اتصال.
                            </p>
                        </div>
                    </template>
                </div>

                <footer class="flex items-center gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <button type="button" x-show="!importPreview?.error" @click="confirmImport()"
                        class="flex-1 rounded-xl bg-primary-500 px-4 py-3 text-sm font-extrabold text-white hover:bg-primary-600"
                        x-text="'استيراد ' + (importPreview?.rows?.length ?? 0) + ' عميل'"></button>
                    <button type="button" @click="cancelImport()"
                        class="rounded-xl border border-zinc-300 px-4 py-3 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        إلغاء
                    </button>
                    <button type="button" @click="downloadTemplate()"
                        class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">
                        نموذج CSV
                    </button>
                </footer>
            </div>
        </div>

        {{-- WhatsApp composer --}}
        <div x-show="compose" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeSend()"></div>

            <div class="relative flex max-h-full w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900"
                @keydown.escape.window="closeSend()">
                <header class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-extrabold">إرسال رسالة واتساب</h2>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400"
                        x-text="compose?.bulk
                            ? 'إلى ' + compose.targets.length + ' عميل من القائمة المعروضة'
                            : 'إلى ' + (compose?.targets[0]?.name ?? '')"></p>
                </header>

                <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                    <template x-if="!sendResult">
                        <div class="space-y-3">
                            <textarea x-model="composeText" rows="6" class="{{ $input }} leading-relaxed"
                                placeholder="اكتب نص الرسالة…"></textarea>

                            <button type="button" @click="composeText += '{الاسم}'"
                                class="rounded-lg bg-zinc-100 px-2.5 py-1.5 text-xs font-bold hover:bg-primary-500/15 hover:text-primary-600 dark:bg-zinc-800 dark:hover:text-primary-300">
                                + إدراج {الاسم}
                            </button>
                            <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                يُستبدل <code class="font-mono">{الاسم}</code> باسم كل عميل عند الإرسال.
                                تُرسل الرسائل عبر الطابور بفواصل زمنية عشوائية، لذا يستغرق الإرسال الجماعي بعض الوقت.
                            </p>
                        </div>
                    </template>

                    <template x-if="sendResult">
                        <div class="rounded-xl px-4 py-3 text-sm font-bold"
                            :class="sendResult.ok
                                ? 'bg-primary-500/10 text-primary-700 dark:text-primary-300'
                                : 'bg-red-500/10 text-red-600 dark:text-red-400'">
                            <p x-text="sendResult.message"></p>
                            <p x-show="sendResult.ok && sendResult.skipped" class="mt-1 text-xs font-normal"
                                x-text="'تم تخطي ' + sendResult.skipped + ' سجل بسبب رقم هاتف غير صالح.'"></p>
                        </div>
                    </template>
                </div>

                <footer class="flex items-center gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <button type="button" x-show="!sendResult" @click="submitSend()"
                        :disabled="!composeText.trim() || sending"
                        class="flex-1 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-extrabold text-white hover:bg-emerald-700 disabled:opacity-50">
                        <span x-show="!sending" x-text="'إرسال إلى ' + (compose?.targets.length ?? 0) + ' رقم'"></span>
                        <span x-show="sending" x-cloak>جارٍ الجدولة…</span>
                    </button>
                    <button type="button" @click="closeSend()"
                        class="rounded-xl border border-zinc-300 px-4 py-3 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        x-text="sendResult ? 'إغلاق' : 'إلغاء'"></button>
                </footer>
            </div>
        </div>

        {{-- Slide-over panel --}}
        <div x-show="panel" x-cloak class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closePanel()" x-show="panel"
                x-transition.opacity></div>

            <div class="panel-slide-enter absolute inset-y-0 left-0 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-zinc-900"
                x-show="panel" x-trap.noscroll="panel" @keydown.escape.window="closePanel()">

                <header class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-extrabold" x-text="editingId ? 'تعديل عميل' : 'عميل جديد'"></h2>
                    <button type="button" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        @click="closePanel()">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                    <div>
                        <label class="{{ $label }}">الاسم</label>
                        <input type="text" x-model="form.name" class="{{ $input }}" placeholder="اسم العميل…">
                        <p class="{{ $error }}" x-show="errors.name" x-text="errors.name"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">رقم الهاتف</label>
                        <input type="tel" x-model="form.phone" dir="ltr" class="{{ $input }}" placeholder="05xxxxxxxx">
                        <p class="{{ $error }}" x-show="errors.phone" x-text="errors.phone"></p>
                    </div>

                    <div>
                        <label class="{{ $label }}">العقار</label>
                        <input type="text" x-model="form.property" class="{{ $input }}" placeholder="فيلا، شقة، اسم المشروع…">
                    </div>

                    <div>
                        <label class="{{ $label }}">التاريخ</label>
                        <input type="date" x-model="form.lead_date" class="{{ $input }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">التصنيف</label>
                        <input type="text" x-model="form.classification" class="{{ $input }}" list="lead-classifications"
                            placeholder="مهتم، تم التواصل، مؤجل…">
                        <datalist id="lead-classifications">
                            <template x-for="value in classifications" :key="value">
                                <option :value="value"></option>
                            </template>
                        </datalist>
                    </div>
                </div>

                <footer class="flex items-center gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <button type="button" @click="save()"
                        class="flex-1 rounded-xl bg-primary-500 px-4 py-3 text-sm font-extrabold text-white hover:bg-primary-600">
                        <span x-text="editingId ? 'حفظ التعديلات' : 'إضافة العميل'"></span>
                        <span class="text-xs font-normal opacity-80" x-show="!$store.sync.online">(سيُزامن عند عودة الاتصال)</span>
                    </button>
                    <button type="button" @click="closePanel()"
                        class="rounded-xl border border-zinc-300 px-4 py-3 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        إلغاء
                    </button>
                </footer>
            </div>
        </div>
    </div>
@endsection
