<div class="w-full flex flex-col lg:flex-row gap-8 p-6" dir="rtl">
    
    {{-- Main Builder Area --}}
    <div class="w-full lg:w-3/4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 p-8">
            <div class="mb-8 flex items-center justify-between">
                <div>
                     <h2 class="text-2xl font-bold text-gray-100">منشئ المقالات</h2>
                     <p class="text-gray-400 text-sm mt-1">اكتب محتوى المقال مع إضافة وسوم HTML بحرية</p>
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

            <div class="flex flex-col gap-8">
                {{-- Basics --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-neutral-700">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-400">عنوان المقال</label>
                        <input type="text" wire:model="title" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all">
                        @error('title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-gray-400">صورة الغلاف</label>
                        <div class="relative">
                            <input type="file" wire:model="image" class="w-full p-3 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200">
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
                    <textarea x-ref="contentArea" wire:model.defer="content" rows="18" class="w-full p-6 rounded-xl bg-neutral-900 border border-neutral-600 text-gray-200 text-lg leading-loose focus:border-[#498e49] focus:ring focus:ring-[#498e49]/20 outline-none transition-all resize-y" dir="auto" placeholder="اكتب محتوى المقال هنا... يمكنك دمج النصوص العادية مع وسوم HTML من الشريط الأعلى..."></textarea>
                    
                    @error('content') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                
                {{-- Action Button --}}
                <div class="mt-4 flex justify-end pb-4">
                     <button wire:click="createArticle" class="bg-[#498e49] hover:bg-[#3d7a3d] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-[#498e49]/20 transition-all text-lg">
                         حفظ المقال
                     </button>
                </div>
            </div>

        </div>
    </div>

    {{-- Sidebar/List of Articles --}}
    <div class="w-full lg:w-1/4 flex flex-col gap-4">
        <div class="bg-neutral-800 rounded-2xl shadow-sm border border-neutral-700 overflow-hidden">
            <div class="bg-[#498e49]/20 p-4 border-b border-[#498e49]/20 flex justify-between items-center">
                <h3 class="font-bold text-[#498e49] text-center w-full">المقالات المنشأة</h3>
            </div>
            <div class="p-2 flex flex-col gap-2 max-h-[600px] overflow-y-auto">
                @foreach (\App\Models\Article::latest()->get() as $article)
                    <div class="flex justify-between items-center bg-neutral-700/50 p-3 rounded-xl text-gray-200 hover:bg-[#498e49] hover:text-white transition-all duration-200 group">
                        <span class="font-medium cursor-pointer flex-grow break-words line-clamp-2 pl-2" title="{{ $article->title }}">{{ $article->title }}</span>
                        <div class="flex items-center gap-1 shrink-0">
                            <a href="{{ route('articles.edit', $article->id) }}" class="text-gray-400 hover:text-blue-200 group-hover:text-white transition-colors p-1" title="تعديل">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </a>
                            <button wire:click="deleteArticle({{ $article->id }})" wire:confirm="هل أنت متأكد من حذف هذا المقال؟" class="text-gray-400 hover:text-red-200 group-hover:text-white transition-colors p-1" title="حذف">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
                @if(\App\Models\Article::count() === 0)
                    <div class="text-center p-4 text-gray-500 text-sm">لا توجد مقالات منشأة بعد</div>
                @endif
            </div>
        </div>
    </div>
</div>
