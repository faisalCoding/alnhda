{{-- Mobile overlay --}}
<div class="fixed inset-0 z-40 bg-black/40 lg:hidden" x-show="sidebarOpen" x-transition.opacity x-cloak
    @click="sidebarOpen = false"></div>

<aside
    class="fixed inset-y-0 right-0 z-50 flex w-72 flex-col border-l border-zinc-200 bg-white transition-transform duration-200 dark:border-zinc-800 dark:bg-zinc-900 lg:static lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'">

    <div class="flex items-center justify-between gap-3 px-6 py-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-500 text-lg font-extrabold text-white">ن</span>
            <span>
                <span class="block text-base font-extrabold">كيان النهضة</span>
                <span class="block text-xs text-zinc-500 dark:text-zinc-400">لوحة التحكم</span>
            </span>
        </a>
        <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 lg:hidden"
            @click="sidebarOpen = false" aria-label="إغلاق القائمة">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-2">
        @php
            $navItems = [
                [
                    'route' => 'dashboard',
                    'label' => 'نظرة عامة',
                    'icon' => 'M2.25 12 11.204 3.045c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
                ],
                [
                    'route' => 'projects-dashboard',
                    'label' => 'المشاريع والوحدات',
                    'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21',
                ],
                [
                    'route' => 'articles-dashboard',
                    'label' => 'المقالات',
                    'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
                ],
                [
                    'route' => 'leads-dashboard',
                    'label' => 'العملاء المحتملون',
                    'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
                ],
                [
                    'route' => 'whatsapp-dashboard',
                    'label' => 'ربط الواتساب',
                    'icon' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z',
                ],
                [
                    'route' => 'whatsapp-messages',
                    'label' => 'سجل الرسائل',
                    'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
                ],
                [
                    'route' => 'visitors-dashboard',
                    'label' => 'الزوار والطلبات',
                    'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
                ],
            ];
        @endphp

        @foreach ($navItems as $item)
            <a href="{{ route($item['route']) }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs($item['route']) ? 'bg-primary-500/10 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                </svg>
                <span>{{ $item['label'] }}</span>

                @if ($item['route'] === 'dashboard')
                    <span x-data x-show="$store.sync.failedCount > 0" x-cloak
                        class="mr-auto rounded-full bg-red-500/15 px-2 py-0.5 text-xs font-bold text-red-600 dark:text-red-400"
                        x-text="$store.sync.failedCount"></span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="space-y-3 border-t border-zinc-200 px-4 py-4 dark:border-zinc-800">
        <button type="button" onclick="panelToggleDark()"
            class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
            <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
            </svg>
            <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
            <span class="dark:hidden">الوضع الداكن</span>
            <span class="hidden dark:inline">الوضع الفاتح</span>
        </button>

        <div class="flex items-center gap-3 rounded-xl bg-zinc-50 px-3 py-3 dark:bg-zinc-800/60">
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-500/15 text-sm font-bold text-primary-600 dark:text-primary-300">
                {{ auth('admin')->user()?->initials() }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-bold">{{ auth('admin')->user()?->name }}</span>
                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth('admin')->user()?->email }}</span>
            </span>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-200 hover:text-red-500 dark:hover:bg-zinc-700"
                    title="تسجيل الخروج">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>
