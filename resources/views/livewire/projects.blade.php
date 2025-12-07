<div class=" w-full flex ">

    <div class="w-5/12 flex flex-col">
        <div class="text-right text-lg py-2 px-2 border-r-2 border-r-violet-500">المشاريع المنشأة</div>
        <div class="flex flex-col justify-center items-center gap-2 w-full px-2 text-right text-violet-200 text-lg ">
            @foreach (\App\Models\Project::get() as $project)
                <div class="flex justify-between items-center bg-violet-500 py-2 px-3 rounded w-full  select-none hover:scale-101 hover:bg-violet-600 duration-75 group">
                    <span class="cursor-pointer flex-grow">{{ $project->name }}</span>
                    <button wire:click="deleteProject({{ $project->id }})" wire:confirm="Are you sure you want to delete this project?" class="text-white hover:text-red-300 transition-colors p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
    <div class="flex flex-col gap-5 p-3.5 text-right text-violet-200 mb-3 w-7/12">
        @if ($errors->any())
            <div class="fixed top-4 left-4 z-50 w-full max-w-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                @foreach ($errors->all() as $error)
                    <div class="mb-2 w-full bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative shadow-lg" role="alert">
                        <span class="block sm:inline">{{ $error }}</span>
                        <span class="absolute top-0 bottom-0 right-0 px-4 py-3" @click="show = false">
                            <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

        <h1 class=" text-xl text-violet-200 text-right mb-5">اضف مشروع</h1>

        <div class="flex flex-row-reverse gap-2 w-full">
            <div class="flex flex-col">
                <label for="project.name" class="text-violet-200 mb-3"> اسم المشروع </label>
                <input placeholder="اسم المشروع" type="text" wire:model="project.name" id="project.name"
                    class=" py-2 rounded-full placeholder:text-white border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>

        </div>
        <div class="">
            <div class="flex flex-col">
                <label for="project.description" class="text-violet-200 mb-3">الوصف</label>
                <textarea placeholder="الوصف" type="text" wire:model="project.description" id="project.description"
                    class=" py-2 rounded-2xl placeholder:text-white border-2 border-gray-400 focus:border-violet-600 bg-neutral-800 text-violet-300 text-center"></textarea>
            </div>
        </div>
        <div class="">
            <div class="flex flex-col">
                <label for="project.location" class="text-violet-200 mb-3">الموقع</label>
                <input placeholder="الموقع" type="text" wire:model="project.location" id="project.location"
                    class=" py-2 rounded-full placeholder:text-white border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>
        </div>

        <div class="flex flex-row-reverse gap-2 w-full">
            <div class="flex flex-col">
                <label for="project.project_type" class="text-violet-200 mb-3"> النوع </label>
                <select name="" id="" wire:model="project.project_type"
                    class="py-3 px-10 rounded-full placeholder:text-white border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
                    <option value="فيلا">فيلا</option>
                    <option value="دور">دور</option>
                    <option value="دور">شقة</option>
                </select>

            </div>
            <div class="flex flex-col ">
                <label for="project.status" class="text-violet-200 mb-3">الحالة</label>
                <select name="" id="" wire:model="project.status"
                    class="py-3 px-10 rounded-full placeholder:text-white border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
                    <option value="جديد">جديد</option>
                    <option value="تحت الانشاء">تحت الانشاء</option>
                </select>
            </div>
        </div>
        <label for="projectimage" class="text-violet-200">اختر صورة للمشروع</label>
        <input accept="image/png, image/jpeg" placeholder="ارف ملف الصورة" type="file" wire:model="image"
            id="projectimage"
            class="py-2 rounded-full placeholder:text-white border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
        <button class=" bg-violet-300 text-violet-950 text-center px-7 py-2 rounded-full shadow shadow-violet-950 mt-5"
            wire:click="createProject">رفع</button>
    </div>


</div>
