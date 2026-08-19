{{-- Account form --}}
<div x-show="showForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="showForm = false" @keydown.escape.window="showForm = false">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="showForm">
        <h2 class="mb-5 text-lg font-extrabold" x-text="form.id ? 'تعديل الحساب' : 'حساب جديد'"></h2>

        <form @submit.prevent="saveAccount()" class="space-y-4">
            <div>
                <label class="{{ $label }}">اسم المنصة</label>
                <input type="text" x-model="form.name" class="{{ $input }}" placeholder="إنستغرام">
                <p class="{{ $error }}" x-show="formErrors.name" x-text="formErrors.name?.[0]"></p>
            </div>

            <div>
                <label class="{{ $label }}">التصنيف</label>
                <select x-model.number="form.account_category_id" class="{{ $input }}">
                    <option value="">بلا تصنيف</option>
                    <template x-for="category in categories" :key="category.id">
                        <option :value="category.id" x-text="category.name"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-zinc-400" x-show="!categories.length">أنشئ تصنيفاً من زر «التصنيفات» أولاً</p>
            </div>

            <div>
                <label class="{{ $label }}">اسم المستخدم أو البريد أو رقم الهاتف</label>
                <input type="text" x-model="form.identifier" class="{{ $input }}" dir="ltr">
                <p class="{{ $error }}" x-show="formErrors.identifier" x-text="formErrors.identifier?.[0]"></p>
            </div>

            <div>
                <label class="{{ $label }}">كلمة المرور</label>
                <input type="password" x-model="form.password" class="{{ $input }}" dir="ltr" autocomplete="new-password">
                <p class="mt-1 text-xs text-zinc-400" x-show="form.id">اتركها فارغة للإبقاء على كلمة المرور الحالية</p>
                <p class="{{ $error }}" x-show="formErrors.password" x-text="formErrors.password?.[0]"></p>
            </div>

            <p class="rounded-xl bg-zinc-100 px-3 py-2 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400" x-show="!form.id">
                ستُنسخ قائمة المهام النموذجية إلى هذا الحساب تلقائياً.
            </p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="showForm = false" class="{{ $ghost }}">إلغاء</button>
                <button type="submit" :disabled="saving" class="{{ $primary }}" x-text="saving ? 'جارٍ الحفظ…' : 'حفظ'"></button>
            </div>
        </form>
    </div>
</div>

{{-- Categories --}}
<div x-show="showCategories" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="showCategories = false" @keydown.escape.window="showCategories = false">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="showCategories">
        <h2 class="mb-1 text-lg font-extrabold">التصنيفات</h2>
        <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">
            حذف تصنيف لا يحذف حساباته، بل يتركها بلا تصنيف.
        </p>

        <ul class="mb-5 max-h-60 space-y-2 overflow-y-auto">
            <template x-for="category in categories" :key="category.id">
                <li class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3.5 py-2.5 dark:border-zinc-800">
                    <span class="h-3 w-3 shrink-0 rounded-full" :class="classesFor(category.color).dot"></span>
                    <span class="flex-1 truncate text-sm font-bold" x-text="category.name"></span>
                    <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                        x-text="(category.accounts_count ?? 0) + ' حساب'"></span>
                    <button type="button" @click="editCategory(category)"
                        class="shrink-0 text-xs font-bold text-primary-600 hover:underline dark:text-primary-300">تعديل</button>
                    <button type="button" @click="deleteCategory(category)"
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
                <input type="text" x-model="categoryForm.name" placeholder="اسم التصنيف، مثل: تواصل اجتماعي" class="{{ $input }}">
                <p class="{{ $error }}" x-show="categoryErrors.name" x-text="categoryErrors.name?.[0]"></p>
            </div>

            <div>
                <p class="mb-2 text-xs font-bold text-zinc-500 dark:text-zinc-400">اللون</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="color in colors" :key="color">
                        <button type="button" @click="categoryForm.color = color" :aria-label="color"
                            class="h-8 w-8 rounded-full transition" :class="[
                                classesFor(color).dot,
                                categoryForm.color === color ? 'ring-2 ring-offset-2 ring-zinc-900 dark:ring-white dark:ring-offset-zinc-900' : 'hover:scale-110',
                            ]"></button>
                    </template>
                </div>
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

{{-- Task templates --}}
<div x-show="showTemplates" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="showTemplates = false" @keydown.escape.window="showTemplates = false">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="showTemplates">
        <h2 class="mb-1 text-lg font-extrabold">قائمة المهام النموذجية</h2>
        <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">
            تُنسخ إلى كل حساب جديد. تعديلها هنا لا يغيّر الحسابات القائمة.
        </p>

        <ul class="mb-4 max-h-60 space-y-2 overflow-y-auto">
            <template x-for="template in templates" :key="template.id">
                <li class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3.5 py-2.5 dark:border-zinc-800">
                    <span class="flex-1 text-sm" x-text="template.title"></span>
                    <button type="button" @click="deleteTemplate(template)" class="text-xs font-bold text-red-500 hover:underline">حذف</button>
                </li>
            </template>
            <li x-show="!templates.length" x-cloak
                class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-400 dark:border-zinc-700">
                لا توجد مهام نموذجية بعد
            </li>
        </ul>

        <form @submit.prevent="addTemplate()" class="flex gap-2">
            <input type="text" x-model="templateForm" placeholder="مهمة جديدة، مثل: تفعيل التحقق بخطوتين" class="{{ $input }}">
            <button type="submit" class="{{ $primary }}">إضافة</button>
        </form>

        <div class="mt-5 flex justify-end">
            <button type="button" @click="showTemplates = false" class="{{ $ghost }}">إغلاق</button>
        </div>
    </div>
</div>

{{-- PIN prompt --}}
<div x-show="pinPrompt.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="pinPrompt.open = false" @keydown.escape.window="pinPrompt.open = false">
    <div class="w-full max-w-xs rounded-2xl bg-white p-6 text-center shadow-2xl dark:bg-zinc-900" x-trap.noscroll="pinPrompt.open">
        <h2 class="mb-1 text-lg font-extrabold">رمز الإظهار</h2>
        <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">أدخل الرمز المكوّن من أربعة أرقام</p>

        <form @submit.prevent="submitPin()">
            <input type="password" inputmode="numeric" maxlength="4" x-model="pinPrompt.value" dir="ltr"
                class="{{ $input }} text-center text-2xl tracking-[0.5em]" autocomplete="off" x-ref="pinInput"
                x-effect="pinPrompt.open && $nextTick(() => $refs.pinInput?.focus())">
            <p class="{{ $error }}" x-show="pinPrompt.error" x-text="pinPrompt.error"></p>

            <div class="mt-5 flex justify-center gap-2">
                <button type="button" @click="pinPrompt.open = false" class="{{ $ghost }}">إلغاء</button>
                <button type="submit" :disabled="pinPrompt.busy || pinPrompt.value.length !== 4" class="{{ $primary }}"
                    x-text="pinPrompt.busy ? '…' : 'إظهار'"></button>
            </div>
        </form>
    </div>
</div>

{{-- PIN setup --}}
<div x-show="pinSetup.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    x-transition.opacity @click.self="pinSetup.open = false" @keydown.escape.window="pinSetup.open = false">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900" x-trap.noscroll="pinSetup.open">
        <h2 class="mb-1 text-lg font-extrabold">تعيين رمز الإظهار</h2>
        <p class="mb-5 text-sm text-zinc-500 dark:text-zinc-400">
            رمز من أربعة أرقام يُطلب عند كل إظهار لكلمة مرور أو حساب دفع.
        </p>

        <form @submit.prevent="saveRevealPin()" class="space-y-4">
            <div>
                <label class="{{ $label }}">الرمز</label>
                <input type="password" inputmode="numeric" maxlength="4" x-model="pinSetup.pin" dir="ltr"
                    class="{{ $input }} text-center text-xl tracking-[0.4em]" autocomplete="off">
            </div>
            <div>
                <label class="{{ $label }}">تأكيد الرمز</label>
                <input type="password" inputmode="numeric" maxlength="4" x-model="pinSetup.pin_confirmation" dir="ltr"
                    class="{{ $input }} text-center text-xl tracking-[0.4em]" autocomplete="off">
            </div>
            <div>
                <label class="{{ $label }}">كلمة مرور حسابك</label>
                <input type="password" x-model="pinSetup.current_password" class="{{ $input }}" dir="ltr" autocomplete="current-password">
            </div>

            <p class="{{ $error }}" x-show="pinSetup.error" x-text="pinSetup.error"></p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="pinSetup.open = false" class="{{ $ghost }}">إلغاء</button>
                <button type="submit" :disabled="pinSetup.busy" class="{{ $primary }}" x-text="pinSetup.busy ? 'جارٍ الحفظ…' : 'حفظ الرمز'"></button>
            </div>
        </form>
    </div>
</div>
