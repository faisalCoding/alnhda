@php
    /** Alpine expression that resolves to the record, e.g. "project" inside an x-for. */
    $record = $record ?? 'record';
@endphp
<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold"
    :class="{
        'bg-zinc-500/10 text-zinc-500 dark:text-zinc-400': ({{ $record }}._meta?.syncState ?? 'synced') === 'queued',
        'bg-sky-500/15 text-sky-600 dark:text-sky-400': ({{ $record }}._meta?.syncState ?? 'synced') === 'syncing',
        'bg-primary-500/10 text-primary-600 dark:text-primary-300': ({{ $record }}._meta?.syncState ?? 'synced') === 'synced',
        'bg-red-500/15 text-red-600 dark:text-red-400': ({{ $record }}._meta?.syncState ?? 'synced') === 'failed',
    }">
    <span class="h-1.5 w-1.5 rounded-full"
        :class="{
            'bg-zinc-400': ({{ $record }}._meta?.syncState ?? 'synced') === 'queued',
            'bg-sky-500 sync-pulse': ({{ $record }}._meta?.syncState ?? 'synced') === 'syncing',
            'bg-primary-500': ({{ $record }}._meta?.syncState ?? 'synced') === 'synced',
            'bg-red-500': ({{ $record }}._meta?.syncState ?? 'synced') === 'failed',
        }"></span>
    <span
        x-text="({ queued: 'غير مُزامن', syncing: 'قيد المعالجة', synced: 'مُزامن', failed: 'فشل' })[{{ $record }}._meta?.syncState ?? 'synced']"></span>
</span>
