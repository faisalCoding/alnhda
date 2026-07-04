<div x-data x-show="$store.sync.authExpired" x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>

    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900"
        x-trap.noscroll="$store.sync.authExpired">
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-500/15">
            <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
        </div>

        <h2 class="mb-2 text-lg font-extrabold">انتهت جلسة الدخول</h2>
        <p class="mb-5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
            تغييراتك غير المُزامنة محفوظة في هذا المتصفح ولن تُفقد.
            سجّل الدخول من جديد في تبويب آخر ثم اضغط «تحقّق ومتابعة» لاستئناف المزامنة.
        </p>

        <div class="flex items-center gap-3">
            <a href="{{ route('login') }}" target="_blank" rel="noopener"
                class="flex-1 rounded-xl bg-primary-500 px-4 py-2.5 text-center text-sm font-bold text-white hover:bg-primary-600">
                تسجيل الدخول
            </a>
            <button type="button" @click="$store.sync.resume()"
                class="flex-1 rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-bold text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                تحقّق ومتابعة
            </button>
        </div>
    </div>
</div>
