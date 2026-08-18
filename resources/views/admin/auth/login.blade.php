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

    <form method="POST" action="{{ route('admin.login.store') }}" class="flex flex-col gap-5">
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

        <button type="submit"
            class="w-full rounded-xl bg-primary-500 px-4 py-3 text-sm font-extrabold text-white transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40">
            تسجيل الدخول
        </button>
    </form>

    @if (config('app.admin_registration_enabled'))
        <p class="mt-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
            ليس لديك حساب؟
            <a href="{{ route('admin.register') }}" class="font-bold text-primary-600 hover:underline dark:text-primary-300">إنشاء حساب</a>
        </p>
    @endif
@endsection
