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

        <ul class="mb-4 max-h-56 space-y-2 overflow-y-auto">
            <template x-for="template in templates" :key="template.id">
                <li class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3.5 py-2.5 dark:border-zinc-800">
                    <span class="flex-1 text-sm" x-text="template.title"></span>
                    <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                        x-text="template.employee_name ?? 'للجميع'"></span>
                    <button type="button" @click="removeTemplate(template)"
                        class="shrink-0 text-xs font-bold text-red-500 hover:underline">حذف</button>
                </li>
            </template>
            <li x-show="!templates.length" x-cloak
                class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-400 dark:border-zinc-700">
                لا توجد مهام نموذجية بعد
            </li>
        </ul>

        <form @submit.prevent="addTemplate()" class="space-y-3">
            <input type="text" x-model="templateForm.title" placeholder="مهمة أسبوعية، مثل: نشر ٣ منشورات" class="{{ $input }}">
            <p class="{{ $error }}" x-show="templateErrors.title" x-text="templateErrors.title?.[0]"></p>

            <div class="flex gap-2">
                <select x-model.number="templateForm.employee_id" class="{{ $input }}">
                    <option value="">لكل الموظفين</option>
                    <template x-for="employee in employees" :key="employee.id">
                        <option :value="employee.id" x-text="employee.name"></option>
                    </template>
                </select>
                <button type="submit" class="{{ $primary }}">إضافة</button>
            </div>
        </form>

        <div class="mt-5 flex justify-end">
            <button type="button" @click="showTemplates = false" class="{{ $ghost }}">إغلاق</button>
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

        <div class="mb-4 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">المجموعة الحالية</p>
            <p class="mt-0.5 font-bold" x-text="settings.whatsapp_group_name ?? 'لم تُحدَّد بعد'"></p>
        </div>

        <div class="mb-4">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm font-bold">اختر مجموعة</p>
                <button type="button" @click="loadGroups()" :disabled="loadingGroups"
                    class="text-xs font-bold text-primary-600 hover:underline disabled:opacity-50 dark:text-primary-300"
                    x-text="loadingGroups ? 'جارٍ الجلب…' : 'تحديث القائمة'"></button>
            </div>

            <p x-show="groupsError" x-cloak class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                x-text="groupsError"></p>

            <ul class="max-h-48 space-y-1 overflow-y-auto rounded-xl border border-zinc-200 p-2 dark:border-zinc-800">
                <template x-for="group in groups" :key="group.id">
                    <li>
                        <button type="button" @click="chooseGroup(group)"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-right transition hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            :class="settings.whatsapp_group_id === group.id && 'bg-primary-500/10'">
                            <span class="min-w-0 flex-1 truncate text-sm" x-text="group.name"></span>
                            <span x-show="group.participants" class="shrink-0 text-xs text-zinc-400"
                                x-text="group.participants + ' عضو'"></span>
                        </button>
                    </li>
                </template>
                <li x-show="!groups.length && !loadingGroups" x-cloak class="px-2 py-4 text-center text-sm text-zinc-400">
                    لا توجد مجموعات. تأكد من ربط الواتساب أولاً.
                </li>
            </ul>
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
