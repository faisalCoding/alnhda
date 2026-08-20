{{-- Employees --}}
<div x-show="showEmployees" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="showEmployees = false" @keydown.escape.window="showEmployees = false">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="showEmployees">
        <h2 class="mb-1 text-lg font-extrabold">الموظفون</h2>
        <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">
            القوائم تُولَّد للموظفين النشطين فقط، وابتداءً من أسبوع انضمام كل واحد.
        </p>

        <ul class="mb-5 max-h-56 space-y-2 overflow-y-auto">
            <template x-for="employee in employees" :key="employee.id">
                <li class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3.5 py-2.5 dark:border-zinc-800">
                    <span class="h-2 w-2 shrink-0 rounded-full" :class="employee.is_active ? 'bg-emerald-500' : 'bg-zinc-300'"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold" x-text="employee.name"></span>
                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400"
                            x-text="[employee.role, employee.enrolled_on].filter(Boolean).join(' · ')"></span>
                    </span>
                    <button type="button" @click="editEmployee(employee)"
                        class="shrink-0 text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">تعديل</button>
                    <button type="button" @click="removeEmployee(employee)"
                        class="shrink-0 text-xs font-bold text-red-500 hover:underline">حذف</button>
                </li>
            </template>
            <li x-show="!employees.length" x-cloak
                class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-400 dark:border-zinc-700">
                لا يوجد موظفون بعد
            </li>
        </ul>

        <form @submit.prevent="saveEmployee()" class="space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
            <p class="text-sm font-bold" x-text="employeeForm.id ? 'تعديل الموظف' : 'موظف جديد'"></p>

            <div>
                <input type="text" x-model="employeeForm.name" placeholder="الاسم" class="{{ $input }}">
                <p class="{{ $error }}" x-show="employeeErrors.name" x-text="employeeErrors.name?.[0]"></p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <input type="text" x-model="employeeForm.role" placeholder="الدور، مثل: مسوّق" class="{{ $input }}">
                <input type="text" x-model="employeeForm.phone" placeholder="الجوال" class="{{ $input }}" dir="ltr">
            </div>

            <div>
                <label class="{{ $label }}">تاريخ الانضمام</label>
                <input type="date" x-model="employeeForm.enrolled_on" class="{{ $input }}" dir="ltr">
                <p class="mt-1 text-xs text-zinc-400">يُترك فارغاً ليبدأ من اليوم. لا تُولَّد قوائم لأسابيع سبقته.</p>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" x-model="employeeForm.is_active"
                    class="h-4 w-4 rounded border-zinc-300 text-primary-500 focus:ring-primary-500 dark:border-zinc-600">
                <span>نشط</span>
            </label>

            <div class="flex justify-end gap-2">
                <button type="button" x-show="employeeForm.id" @click="openEmployeeCreate()" class="{{ $ghost }}">إلغاء التعديل</button>
                <button type="submit" class="{{ $primary }}" x-text="employeeForm.id ? 'حفظ' : 'إضافة'"></button>
            </div>
        </form>

        <div class="mt-5 flex justify-end">
            <button type="button" @click="showEmployees = false" class="{{ $ghost }}">إغلاق</button>
        </div>
    </div>
</div>

{{-- Weekly templates --}}
<div x-show="showTemplates" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="showTemplates = false" @keydown.escape.window="showTemplates = false">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="showTemplates">
        <h2 class="mb-1 text-lg font-extrabold">المهام النموذجية</h2>
        <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">
            تُنسخ إلى قوائم الأسبوع عند التوليد. مهمة بلا موظف محدد تذهب للجميع.
        </p>

        <div class="mb-4 max-h-56 space-y-3 overflow-y-auto">
            <template x-for="group in groupedTemplates" :key="group.key">
                <section>
                    <p class="mb-1.5 flex items-center gap-1.5">
                        <span class="h-2 w-2 shrink-0 rounded-full" :class="classesFor(group.color).dot"></span>
                        <span class="text-xs font-extrabold text-zinc-500 dark:text-zinc-400" x-text="group.name"></span>
                    </p>

                    <ul class="space-y-2">
                        <template x-for="template in group.items" :key="template.id">
                            <li class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3.5 py-2.5 dark:border-zinc-800">
                                <span class="min-w-0 flex-1 truncate text-sm" x-text="template.title"></span>
                                <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                                    x-text="template.employee_name ?? 'للجميع'"></span>
                                <button type="button" @click="removeTemplate(template)"
                                    class="shrink-0 text-xs font-bold text-red-500 hover:underline">حذف</button>
                            </li>
                        </template>
                    </ul>
                </section>
            </template>

            <p x-show="!templates.length" x-cloak
                class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-400 dark:border-zinc-700">
                لا توجد مهام نموذجية بعد
            </p>
        </div>

        <form @submit.prevent="addTemplate()" class="space-y-3">
            <div>
                <textarea x-model="templateForm.title" rows="3" class="{{ $input }}"
                    placeholder="مهمة في كل سطر، مثل:&#10;نشر ٣ منشورات&#10;متابعة العملاء المحتملين"></textarea>
                <div class="mt-1 flex items-center justify-between">
                    <p class="text-xs text-zinc-400">اكتب أو الصق عدة مهام دفعة واحدة — مهمة في كل سطر.</p>
                    <p x-show="pendingTemplateCount > 1" x-cloak class="text-xs font-bold text-primary-600 dark:text-primary-300"
                        x-text="pendingTemplateCount + ' مهام'"></p>
                </div>
                <p class="{{ $error }}" x-show="templateErrors.title" x-text="templateErrors.title?.[0]"></p>
            </div>

            <div class="flex gap-2">
                <select x-model.number="templateForm.employee_id" class="{{ $input }}" :disabled="!employees.length">
                    <option value="">لكل الموظفين</option>
                    <template x-for="employee in employees" :key="employee.id">
                        <option :value="employee.id" x-text="employee.name"></option>
                    </template>
                </select>

                <select x-model.number="templateForm.weekly_task_category_id" class="{{ $input }}">
                    <option value="">بلا تصنيف</option>
                    <template x-for="category in categories" :key="category.id">
                        <option :value="category.id" x-text="category.name"></option>
                    </template>
                </select>

                <button type="submit" class="{{ $primary }}">إضافة</button>
            </div>

            <p x-show="!categories.length" x-cloak class="text-xs text-zinc-400">
                لتقسيم المهام إلى مجموعات أنشئ
                <button type="button" @click="showTemplates = false; showCategories = true"
                    class="font-bold text-primary-600 underline hover:no-underline dark:text-primary-300">تصنيفاً أولاً</button>.
            </p>

            {{-- Without this the dropdown just reads "لكل الموظفين" and looks broken. --}}
            <p x-show="!employees.length" x-cloak class="text-xs text-zinc-400">
                لإسناد مهمة إلى موظف بعينه أضف الموظفين أولاً من
                <button type="button" @click="showEmployees = true"
                    class="font-bold text-primary-600 underline hover:no-underline dark:text-primary-300">قائمة الموظفين</button>.
            </p>
        </form>

        <div class="mt-5 flex justify-end">
            <button type="button" @click="showTemplates = false" class="{{ $ghost }}">إغلاق</button>
        </div>
    </div>
</div>

{{-- Categories --}}
<div x-show="showCategories" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="showCategories = false" @keydown.escape.window="showCategories = false">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="showCategories">
        <h2 class="mb-1 text-lg font-extrabold">تصنيفات المهام</h2>
        <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">
            تقسّم مهام الأسبوع إلى مجموعات، وتظهر بعناوينها في القائمة وفي رسالة الواتساب.
        </p>

        <ul class="mb-5 max-h-56 space-y-2 overflow-y-auto">
            <template x-for="category in categories" :key="category.id">
                <li class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3.5 py-2.5 dark:border-zinc-800">
                    <span class="h-3 w-3 shrink-0 rounded-full" :class="classesFor(category.color).dot"></span>
                    <span class="min-w-0 flex-1 truncate text-sm font-bold" x-text="category.name"></span>
                    <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                        x-text="(category.templates_count ?? 0) + ' مهمة'"></span>
                    <button type="button" @click="editCategory(category)"
                        class="shrink-0 text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">تعديل</button>
                    <button type="button" @click="removeCategory(category)"
                        class="shrink-0 text-xs font-bold text-red-500 hover:underline">حذف</button>
                </li>
            </template>
            <li x-show="!categories.length" x-cloak
                class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-400 dark:border-zinc-700">
                لا توجد تصنيفات بعد
            </li>
        </ul>

        <form @submit.prevent="saveCategory()" class="space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
            <p class="text-sm font-bold" x-text="categoryForm.id ? 'تعديل التصنيف' : 'تصنيف جديد'"></p>

            <div>
                <input type="text" x-model="categoryForm.name" placeholder="اسم التصنيف، مثل: التسويق" class="{{ $input }}">
                <p class="{{ $error }}" x-show="categoryErrors.name" x-text="categoryErrors.name?.[0]"></p>
            </div>

            <div class="flex flex-wrap gap-2">
                <template x-for="color in colors" :key="color">
                    <button type="button" @click="categoryForm.color = color"
                        class="h-7 w-7 rounded-full ring-offset-2 transition dark:ring-offset-zinc-800"
                        :class="[classesFor(color).dot, categoryForm.color === color ? 'ring-2 ' + classesFor(color).ring : '']"
                        :aria-label="color"></button>
                </template>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" x-show="categoryForm.id" @click="openCategoryCreate()" class="{{ $ghost }}">إلغاء التعديل</button>
                <button type="submit" class="{{ $primary }}" x-text="categoryForm.id ? 'حفظ' : 'إضافة'"></button>
            </div>
        </form>

        <div class="mt-5 flex justify-end">
            <button type="button" @click="showCategories = false" class="{{ $ghost }}">إغلاق</button>
        </div>
    </div>
</div>

{{-- Report settings --}}
<div x-show="showSettings" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="showSettings = false" @keydown.escape.window="showSettings = false">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="showSettings">
        <h2 class="mb-1 text-lg font-extrabold">مجموعة التقارير</h2>
        <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">
            تُرسل مهام الأسبوع كل سبت، وملخص الإنجاز كل خميس، إلى المجموعة المختارة.
        </p>

        <div class="mb-4">
            <label class="{{ $label }}">اسم المجموعة</label>
            <div class="flex gap-2">
                <input type="text" x-model="groupSearch" @keydown.enter.prevent="resolveGroup()"
                    placeholder="اسم المجموعة أو معرّفها المنتهي بـ ‎@g.us" class="{{ $input }}">
                <button type="button" @click="resolveGroup()" :disabled="resolving" class="{{ $ghost }}"
                    x-text="resolving ? '…' : 'بحث بالاسم'"></button>
            </div>
            <p class="mt-1.5 text-xs text-zinc-400">
                الصق المعرّف مباشرة إن كان لديك — يُعتمد فوراً بلا بحث. وإلا فالتقطه بالطريقة أدناه.
            </p>

            <div class="mt-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                <p class="mb-1 text-sm font-bold">التقاط المجموعة</p>
                <ol class="mb-3 list-decimal space-y-0.5 pr-4 text-xs text-zinc-500 dark:text-zinc-400">
                    <li>افتح المجموعة في واتساب وأرسل فيها أي رسالة.</li>
                    <li>ارجع هنا واضغط «التقاط».</li>
                    <li>اختر المجموعة من القائمة التي تظهر.</li>
                </ol>

                <button type="button" @click="captureGroups()" :disabled="capturing" class="{{ $ghost }}"
                    x-text="capturing ? 'جارٍ الالتقاط…' : 'التقاط'"></button>

                <ul x-show="captured.length" x-cloak class="mt-2 space-y-1">
                    <template x-for="group in captured" :key="group.id">
                        <li>
                            <button type="button" @click="chooseGroup(group)"
                                class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-right transition hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                :class="settings.whatsapp_group_id === group.id && 'bg-primary-500/10'">
                                <span class="min-w-0 flex-1 truncate text-sm" x-text="group.name || group.id"></span>
                                <span class="shrink-0 text-xs text-zinc-400" x-text="seenAgo(group.lastSeenAt)"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            <p x-show="groupsError" x-cloak
                class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                x-text="groupsError"></p>

            <ul x-show="candidates.length" x-cloak class="mt-2 space-y-1 rounded-xl border border-zinc-200 p-2 dark:border-zinc-800">
                <template x-for="group in candidates" :key="group.id">
                    <li>
                        <button type="button" @click="chooseGroup(group)"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-right transition hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span class="min-w-0 flex-1 truncate text-sm" x-text="group.name"></span>
                            <span x-show="group.participants" class="shrink-0 text-xs text-zinc-400"
                                x-text="group.participants + ' عضو'"></span>
                        </button>
                    </li>
                </template>
            </ul>

            <div x-show="settings.whatsapp_group_id" x-cloak
                class="mt-2 rounded-xl bg-emerald-50 px-3 py-2 dark:bg-emerald-900/30">
                <p class="flex items-center gap-1.5 text-xs font-bold text-emerald-700 dark:text-emerald-200">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span x-text="'معتمدة: ' + settings.whatsapp_group_name"></span>
                </p>
                <p class="mt-0.5 font-mono text-[11px] text-emerald-600 dark:text-emerald-300" dir="ltr"
                    x-text="settings.whatsapp_group_id"></p>
                <button type="button" @click="testGroup()" :disabled="busy"
                    class="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-700 dark:bg-transparent dark:text-emerald-200 dark:hover:bg-emerald-900/40">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                    <span x-text="busy ? 'جارٍ الإرسال…' : 'أرسل رسالة تجريبية الآن'"></span>
                </button>
                <p class="mt-1 text-center text-[11px] text-emerald-600 dark:text-emerald-300">
                    تُرسل فوراً دون حفظ، لتفحصها بنفسك في واتساب قبل الاعتماد.
                </p>
            </div>
        </div>

        <label class="mb-5 flex cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" x-model="settings.weekly_reports_enabled"
                class="h-4 w-4 rounded border-zinc-300 text-primary-500 focus:ring-primary-500 dark:border-zinc-600">
            <span>تفعيل الإرسال التلقائي كل سبت وخميس</span>
        </label>

        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" x-show="settings.is_ready" @click="sendNow('opening')" :disabled="busy" class="{{ $ghost }}">أرسل مهام الأسبوع الآن</button>
            <button type="button" x-show="settings.is_ready" @click="sendNow('closing')" :disabled="busy" class="{{ $ghost }}">أرسل الملخص الآن</button>
            <button type="button" @click="showSettings = false" class="{{ $ghost }}">إلغاء</button>
            <button type="button" @click="saveSettings()" :disabled="busy" class="{{ $primary }}">حفظ</button>
        </div>
    </div>
</div>

{{-- Message preview --}}
<div x-show="preview.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="preview.open = false" @keydown.escape.window="preview.open = false">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="preview.open">
        <h2 class="mb-4 text-lg font-extrabold"
            x-text="preview.kind === 'opening' ? 'رسالة السبت' : 'رسالة الخميس'"></h2>

        <pre class="max-h-80 overflow-y-auto whitespace-pre-wrap rounded-xl bg-zinc-50 p-4 text-sm leading-relaxed dark:bg-zinc-800"
            x-text="preview.busy ? 'جارٍ التحضير…' : preview.message"></pre>

        <div class="mt-5 flex justify-end">
            <button type="button" @click="preview.open = false" class="{{ $ghost }}">إغلاق</button>
        </div>
    </div>
</div>
