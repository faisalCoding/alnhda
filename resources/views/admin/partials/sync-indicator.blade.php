<div class="flex items-center gap-2 text-xs font-medium" x-data>
    {{-- Offline --}}
    <span x-show="!$store.sync.online" x-cloak
        class="flex items-center gap-1.5 rounded-full bg-amber-500/15 px-3 py-1.5 text-amber-700 dark:text-amber-400">
        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
        غير متصل — سيُزامن تلقائيًا
    </span>

    {{-- Syncing --}}
    <span x-show="$store.sync.online && $store.sync.syncing" x-cloak
        class="flex items-center gap-1.5 rounded-full bg-sky-500/15 px-3 py-1.5 text-sky-700 dark:text-sky-400">
        <span class="sync-pulse h-2 w-2 rounded-full bg-sky-500"></span>
        قيد المعالجة…
    </span>

    {{-- Pending --}}
    <a href="{{ route('dashboard') }}" x-show="$store.sync.pendingCount > 0 && !$store.sync.syncing" x-cloak
        class="flex items-center gap-1.5 rounded-full bg-zinc-500/10 px-3 py-1.5 text-zinc-600 hover:bg-zinc-500/20 dark:text-zinc-300">
        <span class="h-2 w-2 rounded-full bg-zinc-400"></span>
        <span x-text="$store.sync.pendingCount"></span> بانتظار المزامنة
    </a>

    {{-- Failed --}}
    <a href="{{ route('dashboard') }}" x-show="$store.sync.failedCount > 0" x-cloak
        class="flex items-center gap-1.5 rounded-full bg-red-500/15 px-3 py-1.5 text-red-600 hover:bg-red-500/25 dark:text-red-400">
        <span class="h-2 w-2 rounded-full bg-red-500"></span>
        <span x-text="$store.sync.failedCount"></span> فشلت
    </a>

    {{-- All synced --}}
    <span x-show="$store.sync.online && !$store.sync.syncing && !$store.sync.hasWork" x-cloak
        class="flex items-center gap-1.5 rounded-full bg-primary-500/10 px-3 py-1.5 text-primary-700 dark:text-primary-300">
        <span class="h-2 w-2 rounded-full bg-primary-500"></span>
        مُزامن
    </span>
</div>
