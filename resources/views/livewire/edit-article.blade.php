<div class="w-full flex flex-col items-center gap-8 p-6" dir="rtl">
    
    {{-- Main Builder Area --}}
    <div class="w-full lg:w-3/4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 p-8">
            <div class="mb-8 flex items-center justify-between">
                <div>
                     <h2 class="text-2xl font-bold text-gray-100">تعديل المقال</h2>
                     <p class="text-gray-400 text-sm mt-1">تعديل محتوى المقال مع إضافة وسوم HTML بحرية</p>
                </div>
            </div>

            @if (session()->has('message'))
                <div class="fixed top-4 right-4 z-50 w-full max-w-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                    <div class="bg-[#498e49]/90 backdrop-blur-sm text-white p-4 rounded-xl mb-6 border border-[#498e49]/50 shadow-xl flex items-center justify-between">
                        <span>{{ session('message') }}</span>
                         <button @click="show = false" class="text-white/70 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                    </div>
                </div>
            @endif

            <div class="flex flex-col gap-6">
                {{-- Basics --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-neutral-700">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-400">عنوان المقال</label>
                        <input type="text" wire:model="title" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        @error('title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-400">صورة الغلاف</label>
                        <div class="relative flex items-center gap-4">
                            <input type="file" wire:model="image" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200">
                            @if($image)
                                 <img src="{{ $image->temporaryUrl() }}" class="w-16 h-16 rounded object-cover shrink-0 border border-neutral-700">
                            @elseif($existingImage && $existingImage != '\/img\/article.jpg' && $existingImage != '/img/article.jpg')
                                 <img src="{{ asset('storage/' . $existingImage) }}" class="w-16 h-16 rounded object-cover shrink-0 border border-neutral-700">
                            @endif
                        </div>
                        @error('image') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                {{-- Text Area with Alpine Toolbar --}}
                <div class="flex flex-col gap-2" x-data="{
                    insertTag(startTag, endTag) {
                        const textarea = this.$refs.contentArea;
                        const start = textarea.selectionStart;
                        const end = textarea.selectionEnd;
                        const val = textarea.value;
                        const selectedText = val.substring(start, end);
                        const before = val.substring(0, start);
                        const after = val.substring(end, val.length);
                        
                        const newText = before + startTag + selectedText + endTag + after;
                        @this.set('content', newText);
                        
                        setTimeout(() => {
                            textarea.focus();
                            const newCursorPos = start + startTag.length + selectedText.length;
                            textarea.setSelectionRange(newCursorPos, newCursorPos);
                        }, 50);
                    },
                    insertAttr(attrString) {
                         const textarea = this.$refs.contentArea;
                         const start = textarea.selectionStart;
                         const val = textarea.value;
                         const before = val.substring(0, start);
                         const after = val.substring(start, val.length);
                         
                         const newText = before + ' ' + attrString + after;
                         @this.set('content', newText);
                         
                         setTimeout(() => {
                             textarea.focus();
                             const newCursorPos = start + 1 + attrString.length;
                             textarea.setSelectionRange(newCursorPos, newCursorPos);
                         }, 50);
                    }
                }">
                    <label class="text-sm font-medium text-gray-400">محتوى المقال (دعم كتابة منتظمة ووسوم HTML)</label>
                    
                    {{-- Toolbar --}}
                    <div class="flex flex-wrap gap-2 mb-2 p-2 bg-neutral-900 rounded-xl border border-neutral-700">
                        <button type="button" @click="insertTag('\x3cp\x3e', '\x3c/p\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;p&gt;</button>
                        <button type="button" @click="insertTag('\x3ch1\x3e', '\x3c/h1\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;h1&gt;</button>
                        <button type="button" @click="insertTag('\x3ch2\x3e', '\x3c/h2\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;h2&gt;</button>
                        <button type="button" @click="insertTag('\x3ch3\x3e', '\x3c/h3\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;h3&gt;</button>
                        <button type="button" @click="insertTag('\x3ca href=\'#\'\x3e', '\x3c/a\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;a&gt;</button>
                        <button type="button" @click="insertTag('\x3cspan class=\'\'\x3e', '\x3c/span\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;span&gt;</button>
                        <button type="button" @click="insertTag('\x3cdiv class=\'\'\x3e', '\x3c/div\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;div&gt;</button>
                        <button type="button" @click="insertTag('\x3cb\x3e', '\x3c/b\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;b&gt;</button>
                        <button type="button" @click="insertTag('\x3cbr\x3e', '')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;br&gt;</button>
                        <button type="button" @click="insertTag('\x3cimg src=\'\' alt=\'\'\x3e', '')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;img&gt;</button>
                        
                        <div class="w-px h-6 bg-neutral-700 mx-1 align-middle self-center"></div>
                        
                        <button type="button" @click="insertTag('\x3cul\x3e\n\x3cli\x3e', '\x3c/li\x3e\n\x3c/ul\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;ul&gt;</button>
                        <button type="button" @click="insertTag('\x3col\x3e\n\x3cli\x3e', '\x3c/li\x3e\n\x3c/ol\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;ol&gt;</button>
                        <button type="button" @click="insertTag('\x3cli\x3e', '\x3c/li\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;li&gt;</button>
                        
                        <div class="w-px h-6 bg-neutral-700 mx-1 align-middle self-center"></div>
                        
                        <button type="button" @click="insertTag('\x3ctable\x3e\n  \x3cthead\x3e\n    \x3ctr\x3e\n      \x3cth\x3eالعنوان\x3c/th\x3e\n    \x3c/tr\x3e\n  \x3c/thead\x3e\n  \x3ctbody\x3e\n    \x3ctr\x3e\n      \x3ctd\x3e', '\x3c/td\x3e\n    \x3c/tr\x3e\n  \x3c/tbody\x3e\n\x3c/table\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;table&gt;</button>
                        <button type="button" @click="insertTag('\x3ctr\x3e\n  \x3ctd\x3e', '\x3c/td\x3e\n\x3c/tr\x3e')" class="px-3 py-1 bg-neutral-800 text-gray-300 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">&lt;tr&gt;&lt;td&gt;</button>
                        
                        <div class="w-px h-6 bg-neutral-700 mx-1 align-middle self-center"></div>
                        
                        <button type="button" @click="insertAttr('class=\'\'')" class="px-3 py-1 bg-[#498e49]/20 text-[#498e49] border border-[#498e49]/30 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">class=""</button>
                        <button type="button" @click="insertAttr('id=\'\'')" class="px-3 py-1 bg-[#498e49]/20 text-[#498e49] border border-[#498e49]/30 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">id=""</button>
                        <button type="button" @click="insertAttr('style=\'\'')" class="px-3 py-1 bg-[#498e49]/20 text-[#498e49] border border-[#498e49]/30 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">style=""</button>
                        <button type="button" @click="insertAttr('dir=\'rtl\'')" class="px-3 py-1 bg-[#498e49]/20 text-[#498e49] border border-[#498e49]/30 rounded hover:bg-[#498e49] hover:text-white transition-colors text-sm font-mono">dir="rtl"</button>
                    </div>

                    {{-- Textarea --}}
                    <textarea x-ref="contentArea" wire:model.defer="content" rows="20" class="w-full p-6 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 text-lg leading-loose focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all resize-y" dir="auto" placeholder="اكتب محتوى المقال هنا..."></textarea>
                    
                    @error('content') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                
                {{-- Action Button --}}
                <div class="mt-4 flex justify-end pb-4 gap-3">
                     <a href="{{ route('dashboard') }}" class="flex items-center bg-neutral-700 hover:bg-neutral-600 text-white px-8 py-3 rounded-xl font-bold transition-all text-lg">
                         إلغاء
                     </a>
                     <button wire:click="updateArticle" class="bg-[#498e49] hover:bg-[#3d7a3d] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-[#498e49]/20 transition-all text-lg">
                         حفظ התعديلات
                     </button>
                </div>
            </div>

        </div>
    </div>
</div>
