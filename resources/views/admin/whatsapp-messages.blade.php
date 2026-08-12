@extends('admin.layouts.panel')

@section('title', 'سجل الرسائل')
@section('heading', 'سجل الرسائل')

@section('content')
    <div x-data="whatsappMessagesPage()" class="space-y-5">

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @click="refresh()" :disabled="loading"
                class="flex items-center gap-1.5 rounded-xl border border-zinc-300 px-4 py-2 text-sm font-bold text-zinc-600 hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                <svg class="h-4 w-4" :class="loading && 'animate-spin'" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                تحديث
            </button>

            <span class="text-sm text-zinc-500 dark:text-zinc-400" x-text="messages.length + ' رسالة'"></span>

            <a href="{{ route('leads-dashboard') }}"
                class="mr-auto rounded-xl bg-primary-500 px-4 py-2 text-sm font-bold text-white hover:bg-primary-600">
                إرسال رسالة جديدة
            </a>
        </div>

        <div x-show="error" x-cloak
            class="rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm font-bold text-red-600 dark:text-red-400"
            x-text="error"></div>

        <div x-show="!loading && !messages.length && !error" x-cloak
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-14 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            لم تُرسل أي رسائل بعد. ابدأ من صفحة العملاء المحتملين.
        </div>

        <template x-for="message in messages" :key="message.id">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">

                <button type="button" @click="toggle(message.id)"
                    class="flex w-full items-start gap-4 px-5 py-4 text-right hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <svg class="mt-1 h-4 w-4 shrink-0 text-zinc-400 transition-transform"
                        :class="expanded === message.id && 'rotate-90'" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold" x-text="preview(message.body)"></p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <span x-text="formatDate(message.created_at)"></span>
                            <template x-if="message.sender">
                                <span> — <span x-text="message.sender"></span></span>
                            </template>
                            <span> — </span><span x-text="message.recipients_count + ' مستلم'"></span>
                            <template x-if="message.skipped_count">
                                <span class="text-amber-600 dark:text-amber-400">
                                    (تم تخطّي <span x-text="message.skipped_count"></span> لرقم غير صالح)
                                </span>
                            </template>
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap justify-end gap-1.5">
                        <template x-for="status in ['delivered', 'read', 'sent', 'queued', 'failed']" :key="status">
                            <span x-show="message.counts[status]"
                                class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(status)">
                                <span x-text="statusLabel(status)"></span>
                                <span x-text="message.counts[status]"></span>
                            </span>
                        </template>
                    </div>
                </button>

                <div x-show="expanded === message.id" x-cloak
                    class="border-t border-zinc-200 dark:border-zinc-800">

                    <div class="bg-zinc-50 px-5 py-4 dark:bg-zinc-800/40">
                        <p class="mb-1 text-xs font-bold text-zinc-500 dark:text-zinc-400">نص الرسالة</p>
                        <p class="whitespace-pre-wrap text-sm leading-relaxed" x-text="message.body"></p>
                    </div>

                    <div class="flex flex-wrap gap-3 border-t border-zinc-200 px-5 py-3 dark:border-zinc-800">
                        <input type="search" x-model="search" placeholder="بحث بالاسم أو الرقم…"
                            class="min-w-48 flex-1 rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <select x-model="statusFilter"
                            class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="">كل الحالات</option>
                            <option value="delivered">تم الاستلام</option>
                            <option value="read">تمت القراءة</option>
                            <option value="sent">أُرسلت</option>
                            <option value="queued">في الطابور</option>
                            <option value="failed">فشلت</option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 text-right text-xs text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                                <tr>
                                    <th class="px-5 py-2 font-medium">الاسم</th>
                                    <th class="px-5 py-2 font-medium">رقم الهاتف</th>
                                    <th class="px-5 py-2 font-medium">الحالة</th>
                                    <th class="px-5 py-2 font-medium">وقت الإرسال</th>
                                    <th class="px-5 py-2 font-medium">تأكيد الاستلام</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                <template x-for="recipient in recipientsOf(message)" :key="recipient.id">
                                    <tr>
                                        <td class="px-5 py-3 font-medium" x-text="recipient.name"></td>
                                        <td class="px-5 py-3" dir="ltr" x-text="recipient.phone"></td>
                                        <td class="px-5 py-3">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-bold"
                                                :class="statusClass(recipient.status)"
                                                x-text="statusLabel(recipient.status)"></span>
                                            <p x-show="recipient.error" x-cloak
                                                class="mt-1 text-xs text-red-500" x-text="recipient.error"></p>
                                        </td>
                                        <td class="px-5 py-3 text-zinc-500 dark:text-zinc-400"
                                            x-text="formatDate(recipient.sent_at)"></td>
                                        <td class="px-5 py-3 text-zinc-500 dark:text-zinc-400"
                                            x-text="formatDate(recipient.read_at ?? recipient.delivered_at)"></td>
                                    </tr>
                                </template>

                                <tr x-show="!recipientsOf(message).length">
                                    <td colspan="5" class="px-5 py-8 text-center text-zinc-400">
                                        لا نتائج مطابقة.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </template>
    </div>
@endsection
