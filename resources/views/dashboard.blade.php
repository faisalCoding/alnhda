<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="relative flex rounded-xl border border-neutral-200 dark:border-neutral-700 grow">
            <x-placeholder-pattern
                class="absolute -z-1 inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            <div class="w-full flex flex-col items-stretch justify-stretch ">
                <input type="radio" checked name="tabs" id="1">
                <input type="radio" name="tabs" id="2">
                <input type="radio" name="tabs" id="3">
                <div class="bar w-full flex flex-row-reverse *:px-20 *:py-5 *:border-b-2 *:hover:border-b-violet-700 *:border-b-neutral-500 ">
                    <label for="1" class="">المشاريع</label>
                    <label for="2" class="">الوحدات</label>
                    <label for="3" class="">المقالات</label>
                </div>

                <div class="tab1 bg-neutral-800 flex-1">
                     @livewire('Projects')
                </div>
                <div class="tab2 size-full bg-neutral-750 ">
                    @livewire('Properties')
                   
                </div>
                <div class="tab3 size-full bg-neutral-750 ">
                    @livewire('BlogIndex')
                </div>
            </div>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="relative aspect-video overflow-scroll  rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern
                    class="absolute inset-0 -z-1 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                
            </div>
            <div
                class="relative aspect-video overflow-scroll rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern
                    class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20 z-[-1]" />
                
            </div>
            <div
                class="relative aspect-video overflow-scroll rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern
                    class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>
        
    </div>
    <style>
        input[name="tabs"] {
            display: none;
        }
        input[id="1"]:checked ~ div label[for="1"] {
            border-bottom-color: #913cd2 !important;
        }
        input[id="2"]:checked ~ div label[for="2"] {
            border-bottom-color: #913cd2 !important;
        }
        input[id="3"]:checked ~ div label[for="3"] {
            border-bottom-color: #913cd2 !important;
        }

        .tab1,
        .tab2,
        .tab3 {
            display: none;
        }

        input[id="1"]:checked~.tab1,
        input[id="2"]:checked~.tab2,
        input[id="3"]:checked~.tab3 {
            display: flex;
        }
    </style>

<div x-data="{ count: 0 }">
    <!-- Render the current "count" value inside an element... -->
    <h2 x-text="count"></h2>
 
    <!-- Increment the "count" value by "1" when a click event is dispatched... -->
    <button x-on:click="count++">+</button>
</div>
</x-layouts.app>
