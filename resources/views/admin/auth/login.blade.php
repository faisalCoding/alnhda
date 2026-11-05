@extends('admin.layouts.guest')

@section('title', 'تسجيل الدخول')

@section('content')
    <h1 class="mb-1 text-xl font-extrabold">تسجيل الدخول</h1>
    <p class="mb-6 text-sm text-zinc-500 dark:text-zinc-400">أدخل بريدك الإلكتروني وكلمة المرور للمتابعة</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-primary-500/10 px-4 py-3 text-sm font-medium text-primary-700 dark:text-primary-300">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login.store') }}" class="flex flex-col gap-5" data-login-form>
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-bold">البريد الإلكتروني</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                placeholder="email@example.com"
                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800">
            @error('email')
                <p class="mt-1.5 text-xs font-medium text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-bold">كلمة المرور</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••"
                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800">
            @error('password')
                <p class="mt-1.5 text-xs font-medium text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-zinc-300 text-primary-500 focus:ring-primary-500/30"
                {{ old('remember') ? 'checked' : '' }}>
            تذكرني
        </label>

        <button type="submit" data-login-submit
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-500 px-4 py-3 text-sm font-extrabold text-white transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:cursor-wait disabled:opacity-70 disabled:hover:bg-primary-500">
            <svg data-login-spinner class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
            </svg>
            <span data-login-label>تسجيل الدخول</span>
        </button>
    </form>

    {{-- A second click while the first request is still on its way sends another
         login, so one mistyped password used to count twice toward the lockout. --}}
    <script>
        (() => {
            const form = document.querySelector('[data-login-form]');
            const button = form.querySelector('[data-login-submit]');
            const label = button.querySelector('[data-login-label]');
            const spinner = button.querySelector('[data-login-spinner]');
            let releaseTimer = null;

            const setBusy = (busy) => {
                form.dataset.submitting = busy ? 'true' : '';
                button.disabled = busy;
                button.setAttribute('aria-busy', busy ? 'true' : 'false');
                spinner.classList.toggle('hidden', !busy);
                label.textContent = busy ? 'جارٍ تسجيل الدخول…' : 'تسجيل الدخول';
            };

            form.addEventListener('submit', (event) => {
                if (form.dataset.submitting) {
                    event.preventDefault();

                    return;
                }

                setBusy(true);

                // A request that stalls must not leave the form locked for good.
                releaseTimer = setTimeout(() => setBusy(false), 15000);
            });

            // Going back restores this page from the back-forward cache with the button still disabled.
            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    clearTimeout(releaseTimer);
                    setBusy(false);
                }
            });
        })();
    </script>

    @if (config('app.admin_registration_enabled'))
        <p class="mt-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
            ليس لديك حساب؟
            <a href="{{ route('admin.register') }}" class="font-bold text-primary-600 hover:underline dark:text-primary-300">إنشاء حساب</a>
        </p>
    @endif
@endsection
