@extends('admin.layouts.panel')

@section('title', 'المشاريع والوحدات')
@section('heading', 'المشاريع والوحدات')

@php
    $input = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-zinc-700 dark:bg-zinc-800';
    $label = 'mb-1.5 block text-sm font-bold';
    $error = 'mt-1 text-xs font-medium text-red-500';
@endphp

@section('content')
    <div x-data="projectsPage()" class="space-y-6">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex rounded-xl bg-zinc-200/70 p-1 dark:bg-zinc-800">
                <button type="button" @click="switchTab('projects')"
                    class="rounded-lg px-4 py-2 text-sm font-bold transition"
                    :class="activeTab === 'projects' ? 'bg-white shadow dark:bg-zinc-700' : 'text-zinc-500 dark:text-zinc-400'">
                    المشاريع <span class="text-xs" x-text="'(' + $store.data.projects.length + ')'"></span>
                </button>
                <button type="button" @click="switchTab('properties')"
                    class="rounded-lg px-4 py-2 text-sm font-bold transition"
                    :class="activeTab === 'properties' ? 'bg-white shadow dark:bg-zinc-700' : 'text-zinc-500 dark:text-zinc-400'">
                    الوحدات <span class="text-xs" x-text="'(' + $store.data.properties.length + ')'"></span>
                </button>
            </div>

            <div class="flex flex-1 items-center justify-end gap-3">
                <input type="search" x-model="search" placeholder="بحث بالاسم…"
                    class="w-full max-w-xs rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm outline-none focus:border-primary-500 dark:border-zinc-700 dark:bg-zinc-800">
                <button type="button"
                    @click="activeTab === 'projects' ? openCreateProject() : openCreateProperty()"
                    class="flex shrink-0 items-center gap-1.5 rounded-xl bg-primary-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-primary-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span x-text="activeTab === 'projects' ? 'مشروع جديد' : 'وحدة جديدة'"></span>
                </button>
            </div>
        </div>

        {{-- Projects grid --}}
        <div x-show="activeTab === 'projects'">
            <div x-show="!projects.length" class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
                <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد مشاريع بعد</p>
                <p class="mt-1 text-sm text-zinc-400">أنشئ أول مشروع — يعمل حتى دون اتصال بالإنترنت</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <template x-for="project in projects" :key="project.id">
                    <article class="flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="relative h-40 bg-zinc-100 dark:bg-zinc-800">
                            <img x-show="project.image_full_url" :src="project.image_full_url" :alt="project.name"
                                class="h-full w-full object-cover" loading="lazy">
                            <div x-show="!project.image_full_url"
                                class="flex h-full items-center justify-center text-xs font-bold text-zinc-400">
                                بحاجة صورة — ارفعها من نافذة التعديل
                            </div>
                            <span class="absolute right-3 top-3">
                                @include('admin.partials.sync-badge', ['record' => 'project'])
                            </span>
                        </div>

                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <h3 class="truncate font-extrabold" x-text="project.name"></h3>
                            <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-bold">
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 dark:bg-zinc-800" x-text="project.project_type"></span>
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 dark:bg-zinc-800" x-text="project.status"></span>
                                <span class="rounded-full bg-primary-500/10 px-2 py-0.5 text-primary-600 dark:text-primary-300"
                                    x-text="unitsCount(project) + ' وحدة'"></span>
                            </div>
                            <p class="line-clamp-2 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400" x-text="project.description"></p>

                            <div class="mt-auto flex items-center gap-2 pt-2">
                                <button type="button" @click="openEditProject(project)"
                                    class="flex-1 rounded-xl bg-zinc-100 px-3 py-2 text-xs font-bold hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                                    تعديل
                                </button>
                                <button type="button" @click="deleteProject(project)"
                                    class="rounded-xl px-3 py-2 text-xs font-bold text-red-500 hover:bg-red-500/10">
                                    حذف
                                </button>
                            </div>
                        </div>
                    </article>
                </template>
            </div>
        </div>

        {{-- Properties grid --}}
        <div x-show="activeTab === 'properties'" x-cloak>
            <div x-show="!properties.length" class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
                <p class="font-bold text-zinc-500 dark:text-zinc-400">لا توجد وحدات بعد</p>
                <p class="mt-1 text-sm text-zinc-400">أضف وحدة وسيتم ربطها بمشروعها ومزامنتها تلقائيًا</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <template x-for="property in properties" :key="property.id">
                    <article class="flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="relative h-40 bg-zinc-100 dark:bg-zinc-800">
                            <img x-show="property.images?.length" :src="property.images?.[0]?.full_url" :alt="property.name"
                                class="h-full w-full object-cover" loading="lazy">
                            <div x-show="!property.images?.length"
                                class="flex h-full items-center justify-center text-xs font-bold text-zinc-400">
                                لا توجد صور بعد
                            </div>
                            <span class="absolute right-3 top-3">
                                @include('admin.partials.sync-badge', ['record' => 'property'])
                            </span>
                            <span x-show="property.images?.length > 1"
                                class="absolute bottom-2 left-2 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-bold text-white"
                                x-text="property.images.length + ' صور'"></span>
                        </div>

                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="truncate font-extrabold" x-text="property.name"></h3>
                                <span class="shrink-0 text-sm font-extrabold text-primary-600 dark:text-primary-300"
                                    x-text="Number(property.price ?? 0).toLocaleString('ar-SA') + ' ر.س'"></span>
                            </div>
                            <p class="text-xs font-bold text-zinc-400" x-text="projectName(property.project_id)"></p>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                                <span x-text="'غرف: ' + (property.rooms ?? '—')"></span>
                                <span x-text="'دورات مياه: ' + (property.bathrooms ?? '—')"></span>
                                <span x-text="'مساحة: ' + (property.area ?? '—') + ' م²'"></span>
                            </div>

                            <div class="mt-auto flex items-center gap-2 pt-2">
                                <button type="button" @click="openEditProperty(property)"
                                    class="flex-1 rounded-xl bg-zinc-100 px-3 py-2 text-xs font-bold hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                                    تعديل
                                </button>
                                <button type="button" @click="deleteProperty(property)"
                                    class="rounded-xl px-3 py-2 text-xs font-bold text-red-500 hover:bg-red-500/10">
                                    حذف
                                </button>
                            </div>
                        </div>
                    </article>
                </template>
            </div>
        </div>

        {{-- Slide-over panel --}}
        <div x-show="panel" x-cloak class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closePanel()" x-show="panel" x-transition.opacity></div>

            <div class="panel-slide-enter absolute inset-y-0 left-0 flex w-full max-w-2xl flex-col bg-white shadow-2xl dark:bg-zinc-900"
                x-show="panel" x-trap.noscroll="panel !== null" @keydown.escape.window="closePanel()">

                <header class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-extrabold"
                        x-text="panel === 'project'
                            ? (editingId ? 'تعديل مشروع' : 'مشروع جديد')
                            : (editingId ? 'تعديل وحدة' : 'وحدة جديدة')"></h2>
                    <button type="button" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="closePanel()">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <div class="flex-1 space-y-6 overflow-y-auto px-6 py-5">

                    {{-- ============ Project form ============ --}}
                    <template x-if="panel === 'project'">
                        <div class="space-y-5">
                            <div>
                                <label class="{{ $label }}">اسم المشروع</label>
                                <input type="text" x-model="form.name" class="{{ $input }}" placeholder="مثال: مشروع النهضة 15">
                                <p class="{{ $error }}" x-show="errors.name" x-text="errors.name"></p>
                            </div>

                            <div>
                                <label class="{{ $label }}">الوصف</label>
                                <textarea x-model="form.description" rows="4" class="{{ $input }}" placeholder="وصف تفصيلي للمشروع…"></textarea>
                                <p class="{{ $error }}" x-show="errors.description" x-text="errors.description"></p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $label }}">الموقع</label>
                                    <input type="text" x-model="form.location" class="{{ $input }}" placeholder="جدة حي المنار">
                                </div>
                                <div>
                                    <label class="{{ $label }}">رابط الخريطة</label>
                                    <input type="url" x-model="form.map_url" class="{{ $input }}" dir="ltr" placeholder="https://maps.google.com/…">
                                </div>
                                <div>
                                    <label class="{{ $label }}">الحالة</label>
                                    <select x-model="form.status" class="{{ $input }}">
                                        <option value="جديد">جديد</option>
                                        <option value="تحت الإنشاء">تحت الإنشاء</option>
                                        <option value="جديد مكتمل">جديد مكتمل</option>
                                        <option value="مكتمل">مكتمل</option>
                                        <option value="مباع">مباع</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="{{ $label }}">نوع المشروع</label>
                                    <select x-model="form.project_type" class="{{ $input }}">
                                        <option value="فيلا">فيلا</option>
                                        <option value="شقة">شقة</option>
                                        <option value="دور">دور</option>
                                        <option value="عقار">عقار</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="text-sm font-bold">الضمانات</label>
                                    <button type="button" @click="addGuarantee()"
                                        class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">+ إضافة ضمان</button>
                                </div>
                                <div class="space-y-2">
                                    <template x-for="(guarantee, index) in guarantees" :key="index">
                                        <div class="flex items-center gap-2">
                                            <input type="text" x-model="guarantees[index]" class="{{ $input }}" placeholder="مثال: ضمان الهيكل الإنشائي 10 سنوات">
                                            <button type="button" @click="removeGuarantee(index)"
                                                class="shrink-0 rounded-lg p-2 text-zinc-400 hover:bg-red-500/10 hover:text-red-500">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Project files --}}
                            <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                                <h3 class="mb-3 text-sm font-extrabold">الملفات (صورة المشروع وملف PDF)</h3>

                                <template x-if="!editingId">
                                    <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                        احفظ المشروع أولًا ثم افتحه للتعديل لرفع الصورة والملف.
                                    </p>
                                </template>

                                <template x-if="editingId && isTemp(editingId)">
                                    <p class="text-xs leading-relaxed text-amber-600 dark:text-amber-400">
                                        هذا المشروع بانتظار المزامنة — احفظ وزامن أولًا ثم ارفع الملفات.
                                    </p>
                                </template>

                                <template x-if="editingId && !isTemp(editingId)">
                                    <div class="space-y-3">
                                        <p x-show="!$store.sync.online" class="text-xs font-bold text-amber-600 dark:text-amber-400">
                                            رفع الملفات يتطلب اتصالًا بالإنترنت.
                                        </p>

                                        <div class="flex items-center gap-3" x-show="editingRecord?.image_full_url">
                                            <img :src="editingRecord?.image_full_url" class="h-16 w-24 rounded-lg object-cover" alt="">
                                            <span class="text-xs text-zinc-500">الصورة الحالية</span>
                                        </div>

                                        <label class="block">
                                            <span class="mb-1.5 block text-xs font-bold">صورة المشروع (تُستبدل الحالية)</span>
                                            <input type="file" accept="image/*" :disabled="!$store.sync.online"
                                                @change="uploadProjectImage($event, editingRecord)"
                                                class="block w-full text-xs file:ml-3 file:rounded-lg file:border-0 file:bg-primary-500/10 file:px-3 file:py-2 file:text-xs file:font-bold file:text-primary-600 disabled:opacity-50 dark:file:text-primary-300">
                                        </label>

                                        <label class="block">
                                            <span class="mb-1.5 block text-xs font-bold">
                                                ملف PDF
                                                <span x-show="editingRecord?.pdf_url" class="font-normal text-zinc-400">(يوجد ملف حالي — سيُستبدل)</span>
                                            </span>
                                            <input type="file" accept="application/pdf" :disabled="!$store.sync.online"
                                                @change="uploadProjectPdf($event, editingRecord)"
                                                class="block w-full text-xs file:ml-3 file:rounded-lg file:border-0 file:bg-primary-500/10 file:px-3 file:py-2 file:text-xs file:font-bold file:text-primary-600 disabled:opacity-50 dark:file:text-primary-300">
                                        </label>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- ============ Property form ============ --}}
                    <template x-if="panel === 'property'">
                        <div class="space-y-5">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="{{ $label }}">اسم الوحدة</label>
                                    <input type="text" x-model="form.name" class="{{ $input }}" placeholder="مثال: شقة 4 غرف — الدور الأول">
                                    <p class="{{ $error }}" x-show="errors.name" x-text="errors.name"></p>
                                </div>

                                <div class="col-span-2">
                                    <label class="{{ $label }}">المشروع التابع له</label>
                                    <select x-model="form.project_id" class="{{ $input }}">
                                        <option value="">— اختر المشروع —</option>
                                        <template x-for="option in projectOptions" :key="option.id">
                                            <option :value="option.id"
                                                x-text="option.name + (isTemp(option.id) ? ' (بانتظار المزامنة)' : '')"
                                                :selected="String(option.id) === String(form.project_id)"></option>
                                        </template>
                                    </select>
                                    <p class="{{ $error }}" x-show="errors.project_id" x-text="errors.project_id"></p>
                                </div>

                                <div>
                                    <label class="{{ $label }}">السعر (ر.س)</label>
                                    <input type="number" x-model.number="form.price" class="{{ $input }}" min="0">
                                    <p class="{{ $error }}" x-show="errors.price" x-text="errors.price"></p>
                                </div>
                                <div>
                                    <label class="{{ $label }}">سعر العرض (اختياري)</label>
                                    <input type="number" x-model="form.offer" class="{{ $input }}" min="0">
                                </div>

                                <div>
                                    <label class="{{ $label }}">الحالة</label>
                                    <select x-model="form.status" class="{{ $input }}">
                                        <option value="جديد">جديد</option>
                                        <option value="متاح">متاح</option>
                                        <option value="محجوز">محجوز</option>
                                        <option value="مباع">مباع</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="{{ $label }}">النوع</label>
                                    <select x-model="form.type" class="{{ $input }}">
                                        <option value="شقة">شقة</option>
                                        <option value="فيلا">فيلا</option>
                                        <option value="دور">دور</option>
                                        <option value="ملحق">ملحق</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="{{ $label }}">الغرف</label>
                                    <input type="number" x-model.number="form.rooms" class="{{ $input }}" min="0">
                                </div>
                                <div>
                                    <label class="{{ $label }}">دورات المياه</label>
                                    <input type="number" x-model.number="form.bathrooms" class="{{ $input }}" min="0">
                                </div>
                                <div>
                                    <label class="{{ $label }}">الصالات</label>
                                    <input type="number" x-model.number="form.living_rooms" class="{{ $input }}" min="0">
                                </div>
                                <div>
                                    <label class="{{ $label }}">غرفة خادمة</label>
                                    <input type="number" x-model.number="form.mainds_room" class="{{ $input }}" min="0">
                                </div>
                                <div>
                                    <label class="{{ $label }}">المساحة (م²)</label>
                                    <input type="number" x-model.number="form.area" class="{{ $input }}" min="0">
                                </div>
                                <div>
                                    <label class="{{ $label }}">المداخل</label>
                                    <input type="number" x-model.number="form.doors" class="{{ $input }}" min="0">
                                </div>
                                <div>
                                    <label class="{{ $label }}">المواقف</label>
                                    <input type="number" x-model.number="form.parkings" class="{{ $input }}" min="0">
                                </div>
                                <div>
                                    <label class="{{ $label }}">غرفة سائق</label>
                                    <input type="number" x-model.number="form.driver_room" class="{{ $input }}" min="0">
                                </div>

                                <div>
                                    <label class="{{ $label }}">الواجهة</label>
                                    <input type="text" x-model="form.facade" class="{{ $input }}" placeholder="شرقية جنوبية">
                                </div>
                                <div class="flex items-end pb-2">
                                    <label class="flex items-center gap-2 text-sm font-bold">
                                        <input type="checkbox" x-model="form.furniture"
                                            class="h-4 w-4 rounded border-zinc-300 text-primary-500 focus:ring-primary-500/30">
                                        مؤثثة
                                    </label>
                                </div>

                                <div>
                                    <label class="{{ $label }}">فيديو الوحدة (يوتيوب)</label>
                                    <input type="url" x-model="form.unit_youtube" class="{{ $input }}" dir="ltr" placeholder="https://youtu.be/…">
                                </div>
                                <div>
                                    <label class="{{ $label }}">فيديو مراحل البناء (يوتيوب)</label>
                                    <input type="url" x-model="form.stages_building_youtube" class="{{ $input }}" dir="ltr" placeholder="https://youtu.be/…">
                                </div>
                            </div>

                            {{-- Property files --}}
                            <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                                <h3 class="mb-3 text-sm font-extrabold">صور الوحدة وملف PDF</h3>

                                <template x-if="!editingId">
                                    <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                        احفظ الوحدة أولًا ثم افتحها للتعديل لرفع الصور والملف.
                                    </p>
                                </template>

                                <template x-if="editingId && isTemp(editingId)">
                                    <p class="text-xs leading-relaxed text-amber-600 dark:text-amber-400">
                                        هذه الوحدة بانتظار المزامنة — احفظ وزامن أولًا ثم ارفع الملفات.
                                    </p>
                                </template>

                                <template x-if="editingId && !isTemp(editingId)">
                                    <div class="space-y-4">
                                        <p x-show="!$store.sync.online" class="text-xs font-bold text-amber-600 dark:text-amber-400">
                                            رفع الملفات يتطلب اتصالًا بالإنترنت.
                                        </p>

                                        <div x-show="editingRecord?.images?.length" class="grid grid-cols-4 gap-2">
                                            <template x-for="image in (editingRecord?.images ?? [])" :key="image.id">
                                                <div class="group relative">
                                                    <img :src="image.full_url" class="h-20 w-full rounded-lg object-cover" alt="">
                                                    <button type="button" @click="removePropertyImage(editingRecord, image)"
                                                        :disabled="!$store.sync.online"
                                                        class="absolute right-1 top-1 hidden rounded-full bg-red-500 p-1 text-white group-hover:block disabled:opacity-50">
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>

                                        <label class="block">
                                            <span class="mb-1.5 block text-xs font-bold">إضافة صور (يمكن اختيار أكثر من صورة)</span>
                                            <input type="file" accept="image/*" multiple :disabled="!$store.sync.online"
                                                @change="uploadPropertyImages($event, editingRecord)"
                                                class="block w-full text-xs file:ml-3 file:rounded-lg file:border-0 file:bg-primary-500/10 file:px-3 file:py-2 file:text-xs file:font-bold file:text-primary-600 disabled:opacity-50 dark:file:text-primary-300">
                                        </label>

                                        <label class="block">
                                            <span class="mb-1.5 block text-xs font-bold">
                                                ملف PDF
                                                <span x-show="editingRecord?.pdf_url" class="font-normal text-zinc-400">(يوجد ملف حالي — سيُستبدل)</span>
                                            </span>
                                            <input type="file" accept="application/pdf" :disabled="!$store.sync.online"
                                                @change="uploadPropertyPdf($event, editingRecord)"
                                                class="block w-full text-xs file:ml-3 file:rounded-lg file:border-0 file:bg-primary-500/10 file:px-3 file:py-2 file:text-xs file:font-bold file:text-primary-600 disabled:opacity-50 dark:file:text-primary-300">
                                        </label>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <footer class="flex items-center gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <button type="button" @click="panel === 'project' ? saveProject() : saveProperty()"
                        class="flex-1 rounded-xl bg-primary-500 px-4 py-3 text-sm font-extrabold text-white hover:bg-primary-600">
                        <span x-text="editingId ? 'حفظ التعديلات' : 'إنشاء'"></span>
                        <span class="text-xs font-normal opacity-80" x-show="!$store.sync.online">(سيُزامن عند عودة الاتصال)</span>
                    </button>
                    <button type="button" @click="closePanel()"
                        class="rounded-xl border border-zinc-300 px-4 py-3 text-sm font-bold text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        إلغاء
                    </button>
                </footer>
            </div>
        </div>
    </div>
@endsection
