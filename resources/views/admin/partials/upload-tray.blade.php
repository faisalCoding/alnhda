<div x-data x-show="$store.uploads.tasks.length" x-cloak
    class="fixed bottom-4 left-4 z-40 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2">
    <template x-for="task in $store.uploads.tasks" :key="task.key">
        <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-2 flex items-center justify-between gap-2">
                <span class="truncate text-xs font-bold" x-text="task.label"></span>
                <button type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                    @click="$store.uploads.dismiss(task.key)" aria-label="إغلاق">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" x-show="task.status === 'uploading'">
                <div class="h-full rounded-full bg-primary-500 transition-all duration-200" :style="`width: ${task.progress}%`"></div>
            </div>

            <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                <span x-show="task.status === 'uploading'">جاري الرفع… <span x-text="task.progress"></span>%</span>
                <span x-show="task.status === 'done'" class="font-bold text-primary-600 dark:text-primary-300">اكتمل الرفع ✓</span>
                <span x-show="task.status === 'failed'" class="font-bold text-red-500" x-text="task.error"></span>
            </p>
        </div>
    </template>
</div>
