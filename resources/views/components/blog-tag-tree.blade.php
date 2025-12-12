@props(['tags', 'level' => 0])

<div class="flex flex-col gap-4">
    @foreach($tags as $index => $tag)
        <div class="border border-neutral-700 rounded-xl p-4 bg-neutral-800/50" style="margin-right: {{ $level * 20 }}px">
            
            {{-- Tag Header Controls --}}
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs text-neutral-500">Tag Type:</span>
                <select 
                        :value="'{{ $tag['tag_name'] }}'"
                        x-on:change="$wire.updateTag('{{ $tag['id'] }}', 'tag_name', $event.target.value)"
                        class="bg-neutral-900 text-white rounded px-2 py-1 text-sm border border-neutral-600">
                    <option value="div" {{ $tag['tag_name'] == 'div' ? 'selected' : '' }}>div</option>
                    <option value="section" {{ $tag['tag_name'] == 'section' ? 'selected' : '' }}>section</option>
                    <option value="h1" {{ $tag['tag_name'] == 'h1' ? 'selected' : '' }}>h1</option>
                    <option value="h2" {{ $tag['tag_name'] == 'h2' ? 'selected' : '' }}>h2</option>
                    <option value="h3" {{ $tag['tag_name'] == 'h3' ? 'selected' : '' }}>h3</option>
                    <option value="h4" {{ $tag['tag_name'] == 'h4' ? 'selected' : '' }}>h4</option>
                    <option value="h5" {{ $tag['tag_name'] == 'h5' ? 'selected' : '' }}>h5</option>
                    <option value="h6" {{ $tag['tag_name'] == 'h6' ? 'selected' : '' }}>h6</option>
                    <option value="p" {{ $tag['tag_name'] == 'p' ? 'selected' : '' }}>p</option>
                    <option value="span" {{ $tag['tag_name'] == 'span' ? 'selected' : '' }}>span</option>
                    <option value="img" {{ $tag['tag_name'] == 'img' ? 'selected' : '' }}>img</option>
                    <option value="a" {{ $tag['tag_name'] == 'a' ? 'selected' : '' }}>a</option>
                    <option value="button" {{ $tag['tag_name'] == 'button' ? 'selected' : '' }}>button</option>
                </select>

                <input type="text" placeholder="Classes (e.g. p-4 bg-red-500)" 
                       value="{{ $tag['classes'] }}"
                       x-on:blur="$wire.updateTag('{{ $tag['id'] }}', 'classes', $event.target.value)"
                       class="bg-neutral-900 text-white rounded px-2 py-1 text-sm border border-neutral-600 flex-grow">

                <button wire:click="removeTag('{{ $tag['id'] }}')" class="text-red-500 hover:text-red-400 p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Text Content Toggle --}}
            <div class="flex items-center gap-2 mb-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" 
                           {{ $tag['has_text'] ? 'checked' : '' }}
                           x-on:change="$wire.updateTag('{{ $tag['id'] }}', 'has_text', $event.target.checked)"
                           class="rounded bg-neutral-900 border-neutral-600 text-[#498e49]">
                    <span class="text-xs text-neutral-400">Has Content?</span>
                </label>
            </div>

            {{-- Content Input --}}
            @if($tag['has_text'])
                <textarea placeholder="Content..." 
                          x-on:blur="$wire.updateTag('{{ $tag['id'] }}', 'content', $event.target.value)"
                          class="w-full bg-neutral-900 text-white rounded p-2 text-sm border border-neutral-600 mb-2 h-20">{{ $tag['content'] }}</textarea>
            @endif

            {{-- Children --}}
            <div class="pl-4 border-r border-neutral-700/50">
                @if(!empty($tag['children']))
                    <x-blog-tag-tree :tags="$tag['children']" :level="$level + 1" />
                @endif
                
                <button wire:click="addTag('{{ $tag['id'] }}')" class="mt-2 text-xs text-[#498e49] hover:text-[#5ab35a] flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Add Child
                </button>
            </div>

        </div>
    @endforeach
</div>
