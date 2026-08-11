@extends('admin.layouts.panel')

@section('title', 'ربط الواتساب')
@section('heading', 'ربط الواتساب')

@section('content')
    <div x-data="whatsappPage()" class="max-w-2xl space-y-6">

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-full"
                    :class="{
                        'bg-primary-500/15 text-primary-600 dark:text-primary-300': status === 'ready',
                        'bg-red-500/15 text-red-600 dark:text-red-400': status === 'error',
                        'bg-amber-500/15 text-amber-600 dark:text-amber-400': !['ready', 'error'].includes(status),
                    }">
                    <svg x-show="status === 'ready'" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <svg x-show="status === 'error'" x-cloak class="h-7 w-7" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <svg x-show="!['ready', 'error'].includes(status)" x-cloak class="h-6 w-6 animate-spin" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-extrabold" x-text="headline"></h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400" x-text="message"></p>
                    <p class="mt-1 text-xs text-zinc-400" x-show="clientId">
                        معرف الجلسة: <code class="font-mono" x-text="clientId"></code>
                    </p>
                </div>

                <div class="flex shrink-0 flex-col gap-2">
                    <button type="button" x-show="status === 'ready'" @click="disconnect()" :disabled="busy"
                        class="rounded-xl border border-zinc-300 px-3 py-1.5 text-xs font-bold text-zinc-600 hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        إيقاف الاتصال
                    </button>
                    <button type="button" @click="resetSession()" :disabled="busy"
                        class="rounded-xl bg-red-500/10 px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-500/20 disabled:opacity-50 dark:text-red-400">
                        إعادة تعيين الجلسة
                    </button>
                </div>
            </div>

            {{-- QR --}}
            <div x-show="status === 'needs_scan' && qrImage" x-cloak
                class="mt-6 flex flex-col items-center rounded-xl border border-zinc-200 bg-zinc-50 p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="mb-4 text-center text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    افتح واتساب على هاتفك ← الأجهزة المرتبطة ← ربط جهاز، وامسح الرمز:
                </p>
                <div class="inline-block rounded-xl bg-white p-4 shadow-sm">
                    <img :src="qrImage" alt="رمز QR لربط الواتساب" class="size-64">
                </div>
            </div>

            <div x-show="status === 'ready'" x-cloak
                class="mt-6 rounded-xl border border-primary-500/20 bg-primary-500/10 px-4 py-3 text-sm text-primary-700 dark:text-primary-300">
                الخدمة جاهزة. يمكنك الآن إرسال الرسائل من صفحة
                <a href="{{ route('leads-dashboard') }}" class="font-bold underline">العملاء المحتملين</a>.
            </div>

            <div x-show="status === 'error'" x-cloak
                class="mt-6 space-y-2 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <p class="font-bold">تعذّر الوصول إلى خدمة الواتساب.</p>

                <button type="button" @click="startService()" :disabled="busy"
                    class="w-fit rounded-xl bg-red-500/15 px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-500/25 disabled:opacity-50 dark:text-red-300">
                    <span x-show="!busy">تشغيل الخدمة الآن</span>
                    <span x-show="busy" x-cloak>جارٍ التشغيل…</span>
                </button>

                <p class="text-xs leading-relaxed">
                    أو شغّلها يدويًا على الخادم:
                    <code class="mt-1 block font-mono" dir="ltr">cd whatsapp-service &amp;&amp; npm install &amp;&amp; npm start</code>
                </p>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h3 class="mb-3 font-extrabold">كيف يعمل الربط</h3>
            <ul class="list-inside list-disc space-y-2 leading-relaxed text-zinc-600 dark:text-zinc-400">
                <li>يُربط رقمك عبر «الأجهزة المرتبطة» في واتساب — لا يُطلب منك أي كلمة مرور.</li>
                <li>لكل مدير جلسة مستقلة، وتبقى محفوظة على الخادم بعد إعادة التشغيل.</li>
                <li>تُرسل رسائل الحملات عبر طابور المهام بفواصل زمنية عشوائية لتقليل خطر الحظر.</li>
                <li>محليًا يشغّل <code class="font-mono" dir="ltr">composer run dev</code> الخادم والطابور وخدمة الواتساب معًا.</li>
            </ul>
        </section>
    </div>
@endsection
