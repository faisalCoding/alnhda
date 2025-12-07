<x-layouts.app :title="__('Dashboard')">
    <div x-data="{ activeTab: 'projects' }" class="w-full">
        <!-- Tabs Navigation -->
        <div class="flex flex-row-reverse mb-6 border-b border-gray-200 dark:border-gray-700">
            <button 
                @click="activeTab = 'projects'" 
                :class="{ 'border-violet-600 text-violet-600': activeTab === 'projects', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'projects' }"
                class="px-6 py-3 font-medium text-lg border-b-2 transition-colors duration-200 focus:outline-none">
                المشاريع
            </button>
            <button 
                @click="activeTab = 'properties'" 
                :class="{ 'border-violet-600 text-violet-600': activeTab === 'properties', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'properties' }"
                class="px-6 py-3 font-medium text-lg border-b-2 transition-colors duration-200 focus:outline-none">
                الوحدات
            </button>
        </div>

        <!-- Projects Tab Content -->
        <div x-show="activeTab === 'projects'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
             <livewire:projects />
        </div>

        <!-- Properties Tab Content -->
        <div x-show="activeTab === 'properties'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
            <livewire:properties />
        </div>
    </div>
</x-layouts.app>