<div class="w-full">

    <div class="flex flex-col gap-5 p-3.5 text-right text-violet-400">
        @error('catch_upload')
            <span class="error">{{ $message }}</span>
        @enderror
        @error('creating_error')
            <span class="error">{{ $message }}</span>
        @enderror

        <h1 class=" text-xl text-violet-400 text-right mb-5">اضف مشروع</h1>

        <div class="flex flex-row-reverse gap-2 w-full">
            <div class="flex flex-col">
                <label for="title" class="text-violet-400 bg-"> اسم المشروع </label>
                <input placeholder="اسم المقالة" type="text" wire:model="title" id="title"
                    class="py-2 rounded-full placeholder:text-white border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>

        </div>
        <div class="">
            <div class="flex flex-col">
                <label for="content" class="text-violet-400">المحتوى</label>
                <textarea placeholder="المحتوى" type="text" wire:model="content" id="content"
                    class="py-2 rounded-2xl placeholder:text-white border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center"></textarea>

            </div>
        </div>

        <label for="blogimage" class="text-violet-400"> اختر صورة للمقالة</label>
        <input accept="image/png, image/jpeg" placeholder="ارف ملف الصورة" type="file" wire:model="image_blog"
            id="blogimage"
            class="file:mr-4 file:rounded-full file:border-0 file:bg-violet-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-violet-700 hover:file:bg-violet-100 dark:file:bg-violet-600 dark:file:text-violet-100 dark:hover:file:bg-violet-500">
        <button class=" bg-violet-300 text-violet-950 text-center px-7 py-2 rounded-full shadow shadow-violet-950 mt-5"
            wire:click="createBlog">رفع</button>
    </div>

</div>
