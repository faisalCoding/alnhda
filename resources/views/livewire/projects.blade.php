<div class=" w-full flex ">

    <div class="w-5/12 flex flex-col">
        <div class="text-right text-lg py-2 px-2 border-r-2 border-r-violet-500">المشاريع المنشأة</div>
        <div class="flex flex-col justify-center items-center gap-2 w-full px-2 text-right text-violet-200 text-lg ">
            @foreach (\App\Models\Project::get() as $project)
                <div class=" bg-violet-500 py-2 px-3 rounded w-full cursor-pointer select-none hover:scale-101 hover:bg-violet-600 duration-75">
                    {{ $project->name }}
                </div>
            @endforeach
        </div>
    </div>
    <div class="flex flex-col gap-5 p-3.5 text-right text-violet-200 mb-3 w-7/12">
        @error('catch_upload')
            <div class=" text-red-500"> هناك مشكلة حدثت اثناء رفع الملف</div>
            <span class="error">{{ $message }}</span>
        @enderror
        @error('creating_error')
            <div class=" text-red-500"> هناك مشكلة حدثت اثناء انشاء المشروع</div>
            <span class="error">{{ $message }}</span>
        @enderror

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
