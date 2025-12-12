<x-layouts.app :title="__('Projects')">
    <div x-data="{ activeTab: 'projects' }" class="w-full">
        <!-- Tabs Navigation -->
        <div class="flex flex-row-reverse mb-6 bg-neutral-800 p-2 rounded-2xl border border-neutral-700">
            <button @click="activeTab = 'projects'"
                :class="{ 'bg-[#498e49] text-white shadow-lg shadow-[#498e49]/20': activeTab === 'projects', 'text-gray-400 hover:text-white hover:bg-neutral-700': activeTab !== 'projects' }"
                class="px-6 py-3 font-bold text-lg rounded-xl transition-all duration-200 focus:outline-none flex-1">
                المشاريع
            </button>
            <button @click="activeTab = 'properties'"
                :class="{ 'bg-[#498e49] text-white shadow-lg shadow-[#498e49]/20': activeTab === 'properties', 'text-gray-400 hover:text-white hover:bg-neutral-700': activeTab !== 'properties' }"
                class="px-6 py-3 font-bold text-lg rounded-xl transition-all duration-200 focus:outline-none flex-1">
                الوحدات
            </button>
        </div>

        <!-- Projects Tab Content -->
        <div x-show="activeTab === 'projects'" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            <livewire:projects />
        </div>

        <!-- Properties Tab Content -->
        <div x-show="activeTab === 'properties'" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
            style="display: none;">
            <livewire:properties />
        </div>
    </div>
</x-layouts.app>
