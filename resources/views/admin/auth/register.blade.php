@extends('admin.layouts.guest')

@section('title', 'إنشاء حساب مدير')

@section('content')
    <h1 class="mb-1 text-xl font-extrabold">إنشاء حساب مدير</h1>
    <p class="mb-6 text-sm text-zinc-500 dark:text-zinc-400">أدخل بياناتك لإنشاء حساب جديد</p>

    <form method="POST" action="{{ route('admin.register.store') }}" class="flex flex-col gap-5">
        @csrf

        <div>
            <label for="name" class="mb-1.5 block text-sm font-bold">الاسم</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name"
                placeholder="الاسم الكامل"
                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800">
            @error('name')
                <p class="mt-1.5 text-xs font-medium text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-bold">البريد الإلكتروني</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"
                placeholder="email@example.com"
                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800">
            @error('email')
                <p class="mt-1.5 text-xs font-medium text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-bold">كلمة المرور</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="••••••••"
                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800">
            @error('password')
                <p class="mt-1.5 text-xs font-medium text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-bold">تأكيد كلمة المرور</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                placeholder="••••••••"
                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800">
        </div>

        <button type="submit"
            class="w-full rounded-xl bg-primary-500 px-4 py-3 text-sm font-extrabold text-white transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40">
            إنشاء الحساب
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
        لديك حساب بالفعل؟
        <a href="{{ route('login') }}" class="font-bold text-primary-600 hover:underline dark:text-primary-300">تسجيل الدخول</a>
    </p>
@endsection
