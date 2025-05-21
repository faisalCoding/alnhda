<div class=" w-full flex *:placeholder:text-neutral-900!">

    <div class="w-5/12 flex flex-col">
        <div class="text-right text-lg py-2 px-2 border-r-2 border-r-violet-500">المشاريع المنشأة</div>
        <div class="flex flex-col justify-center items-center gap-2 w-full px-2 text-right text-violet-200 text-lg ">
            @foreach (\App\Models\Properties::get() as $properties)
                <div
                    class=" bg-violet-500 py-2 px-3 rounded w-full cursor-pointer select-none hover:scale-101 hover:bg-violet-600 duration-75">
                    {{ $properties->name }}
                </div>
            @endforeach
        </div>
    </div>
    <div class="flex flex-col gap-5 p-3.5 text-right text-violet-200 mb-3 w-7/12">
        @error('catch_upload')
            <div class=" text-red-500"> هناك مشكلة حدثت اثناء رفع الملفات</div>
            <span class="error">{{ $message }}</span>
        @enderror
        @error('creating_error')
            <div class=" text-red-500"> هناك مشكلة حدثت اثناء انشاء الوحدة</div>
            <span class="error">{{ $message }}</span>
        @enderror

        <h1 class=" text-xl text-violet-200 text-right mb-5">اضف وحدة جديدة</h1>

        <div class="flex flex-row-reverse justify-between gap-2 w-full">
            <div class="flex flex-col ">
                <label for="project_id" class="text-violet-200 mb-3">المشروع</label>
                <select name="" id="" wire:model="project_id"
                    class="py-3 px-10 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
                    @foreach (\App\Models\Project::get() as $project)
                    <option value="{{$project->id}}">{{$project->name}}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col">
                <label for="name" class="text-violet-200 mb-3"> اسم الوحدة </label>
                <input placeholder="اسم الوحدة" type="text" wire:model="name" id="name"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>

            <div class="flex flex-col">
                <label for="price" class="text-violet-200 mb-3"> السعر</label>
                <input placeholder="سعر الوحدة" type="text" wire:model="price" id="price"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>


            <div class="flex flex-col">
                <label for="offer" class="text-violet-200 mb-3"> العرض</label>
                <input placeholder="سعر العرض" type="text" wire:model="offer" id="offer"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>


        </div>
        <div class="flex flex-row-reverse justify-between gap-2 w-full">
            <div class="flex flex-col">
                <label for="rooms" class="text-violet-200 mb-3">عدد الغرف</label>
                <input placeholder="عدد الغرف" type="text" wire:model="rooms" id="rooms"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>
            <div class="flex flex-col ">
                <label for="status" class="text-violet-200 mb-3">الحالة</label>
                <select name="" id="" wire:model="status"
                    class="py-3 px-10 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
                    <option value="جديد">جديد</option>
                    <option value="تحت الانشاء">تحت الانشاء</option>
                </select>
            </div>
        </div>

        <div class="flex flex-row-reverse justify-between gap-2 w-full">
            <div class="flex flex-col">
                <label for="bathrooms" class="text-violet-200 mb-3">عدد دورات المياه</label>
                <input placeholder="عدد دورات المياه" type="number" wire:model="bathrooms"
                    id="bathrooms"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>

            <div class="flex flex-col">
                <label for="living_rooms" class="text-violet-200 mb-3">عدد الصالات</label>
                <input placeholder="عدد الصالات" type="text" wire:model="living_rooms"
                    id="living_rooms"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>


            <div class="flex flex-col">
                <label for="mainds_room" class="text-violet-200 mb-3">عدد غدف الماستر</label>
                <input placeholder="عدد غدف الماستر" type="text" wire:model="mainds_room"
                    id="mainds_room"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>

        </div>

        <div class="flex flex-row-reverse justify-between gap-2 w-full">
            <div class="flex flex-col">
                <label for="area" class="text-violet-200 mb-3">المساحة</label>
                <input placeholder="المساحة" type="number" wire:model="area" id="area"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>

            <div class="flex flex-col">
                <label for="doors" class="text-violet-200 mb-3">عدد المداخل</label>
                <input placeholder="عدد المداخل" type="text" wire:model="doors" id="doors"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>


            <div class="flex flex-col">
                <label for="type" class="text-violet-200 mb-3"> النوع </label>
                <select name="" id="" wire:model="type"
                    class="py-3 px-10 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
                    <option value="فيلا">فيلا</option>
                    <option value="دور">دور</option>
                    <option value="دور">شقة</option>
                </select>
            </div>

        </div>

        <div class="flex flex-row-reverse justify-between gap-2 w-full">
            <div class="flex flex-col">
                <label for="parkings" class="text-violet-200 mb-3">مواقف السيارات</label>
                <input placeholder="مواقف السيارات" type="number" wire:model="parkings"
                    id="parkings"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>

            <div class="flex flex-col">
                <label for="driver_room" class="text-violet-200 mb-3">غرفة حارس</label>
                <input placeholder="غرفة حارس" type="text" wire:model="driver_room"
                    id="driver_room"
                    class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
            </div>


            <div class="flex flex-col">
                <label for="facade" class="text-violet-200 mb-3"> الواجهة </label>
                <select name="" id="" wire:model="facade"
                    class="py-3 px-10 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
                    <option value="شمالية">شمالية</option>
                    <option value="جنوبية">جنوبية</option>
                    <option value="شرقية">شرقية</option>
                    <option value="غربية">غربية</option>
                    <option value="شمالية غربية">شمالية غربية</option>
                    <option value="جنوبية غربية">جنوبية غربية</option>
                    <option value="شرقية شمالية">شرقية شمالية</option>
                    <option value="شرقية جنوبية">شرقية جنوبية</option>

                </select>
            </div>

        </div>

        <div class="flex flex-row-reverse gap-2 w-full">
            <label for="furniture" class="text-violet-200 mb-3">مؤثثة ؟</label>
            <input placeholder="غرفة حارس" type="checkbox" wire:model="furniture"
                id="furniture"
                class=" py-2 rounded-full  border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">
        </div>
        <label for="propertiesimage" class="text-violet-200">اختر صور الوحدات</label>

        <input accept="image/png, image/jpeg" placeholder="ارفع ملف الصورة" type="file" wire:model="photos"
            multiple id="propertiesimage"
            class="py-2 rounded-full border-2 border-gray-400 bg-neutral-800 text-violet-300 text-center">

        @error('photos.*')
            <span class="error">{{ $message }}</span>
        @enderror
        <button class=" bg-violet-300 text-violet-950 text-center px-7 py-2 rounded-full shadow shadow-violet-950 mt-5"
            wire:click="createProperties">رفع</button>
    </div>

</div>
