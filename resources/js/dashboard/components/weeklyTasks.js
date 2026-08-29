import { ApiError, request } from '../api';
import { uuid } from '../ids';
import { CATEGORY_COLORS, COLOR_CLASSES } from './accounts';

export default function weeklyTasksPage() {
    return {
        employees: [],
        templates: [],
        categories: [],
        lists: [],
        history: [],
        historyLoading: true,
        openWeeks: [],
        settings: { whatsapp_group_id: null, whatsapp_group_name: null, weekly_reports_enabled: false, is_ready: false },
        groupsError: '',

        loading: true,
        busy: false,
        error: '',
        notice: '',

        employeeForm: { id: null, name: '', phone: '', role: '', enrolled_on: '', is_active: true },
        employeeErrors: {},
        showEmployees: false,

        templateForm: { title: '', employee_id: '', weekly_task_category_id: '' },
        templateErrors: {},
        showTemplates: false,

        colors: CATEGORY_COLORS,
        categoryForm: { id: null, name: '', color: 'emerald' },
        categoryErrors: {},
        showCategories: false,

        showSettings: false,
        preview: { open: false, kind: 'opening', message: '', busy: false },

        newItem: {},
        newItemCategory: {},

        async init() {
            await Promise.all([
                this.loadEmployees(),
                this.loadTemplates(),
                this.loadCategories(),
                this.loadWeek(),
                this.loadSettings(),
                this.loadHistory(),
            ]);
            this.loading = false;
        },

        classesFor(color) {
            return COLOR_CLASSES[color] ?? COLOR_CLASSES.zinc;
        },

        async loadEmployees() {
            try {
                this.employees = (await request('GET', '/api/employees'))?.data ?? [];
            } catch (error) {
                this.error = error.message;
            }
        },

        async loadTemplates() {
            try {
                this.templates = (await request('GET', '/api/weekly-task-templates'))?.data ?? [];
            } catch {
                this.templates = [];
            }
        },

        async loadCategories() {
            try {
                this.categories = (await request('GET', '/api/weekly-task-categories'))?.data ?? [];
            } catch {
                this.categories = [];
            }
        },

        async loadWeek() {
            try {
                this.lists = (await request('GET', '/api/weekly-tasks'))?.data ?? [];
            } catch (error) {
                this.error = error.message;
            }
        },

        /**
         * أسابيع مضت. تُحمَّل مرة واحدة مع الصفحة لأن الكروت مطويّة، فلا شيء
         * منها يُرسم قبل أن يفتحها أحد.
         */
        async loadHistory() {
            try {
                this.history = (await request('GET', '/api/weekly-tasks/history'))?.data ?? [];
            } catch {
                // الأرشيف ملحق بالصفحة لا أساسها: فشله لا يجوز أن يُخفي أسبوع اليوم.
                this.history = [];
            }

            this.historyLoading = false;
        },

        toggleWeek(weekStart) {
            this.openWeeks = this.openWeeks.includes(weekStart)
                ? this.openWeeks.filter((open) => open !== weekStart)
                : [...this.openWeeks, weekStart];
        },

        isWeekOpen(weekStart) {
            return this.openWeeks.includes(weekStart);
        },

        percentOf(done, total) {
            return total === 0 ? 0 : Math.round((done / total) * 100);
        },

        async loadSettings() {
            try {
                this.settings = (await request('GET', '/api/weekly-report-settings'))?.data ?? this.settings;
                this.groupSearch = this.settings.whatsapp_group_name ?? '';
            } catch {
                // leave the defaults
            }
        },

        // ---- the week -------------------------------------------------------

        async generate() {
            this.busy = true;
            this.notice = '';

            try {
                const payload = await request('POST', '/api/weekly-tasks/generate', {}, { idempotencyKey: uuid() });
                const { created, topped_up: toppedUp, added } = payload.data;
                this.notice = added === 0 && created === 0
                    ? 'كل القوائم محدّثة بالفعل، لا جديد يُضاف.'
                    : `أُنشئت ${created} قائمة، وأُكملت ${toppedUp} قائمة بـ ${added} مهمة.`;
                await this.loadWeek();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },

        /**
         * يسحب متأخّرات الأسبوع الماضي إلى الأسبوع الحالي. يُمرَّر تاريخ من
         * الأسبوع الماضي — والخادم يردّه إلى سبته — بدل حساب بداية الأسبوع هنا،
         * فحدّ الأسبوع تعريف واحد يجب أن يبقى في مكان واحد.
         */
        async carryForward() {
            this.busy = true;
            this.notice = '';

            const lastWeek = new Date();
            lastWeek.setDate(lastWeek.getDate() - 7);

            try {
                const payload = await request(
                    'POST',
                    '/api/weekly-tasks/carry-forward',
                    { date: lastWeek.toISOString().slice(0, 10) },
                    { idempotencyKey: uuid() }
                );

                const { carried, employees } = payload.data;

                this.notice = carried === 0
                    ? 'لا متأخّرات في الأسبوع الماضي — أو أنها مُرحَّلة بالفعل.'
                    : `رُحّلت ${carried} مهمة لـ ${employees} موظفًا.`;

                await Promise.all([this.loadWeek(), this.loadHistory()]);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },

        async toggleItem(list, item) {
            const next = !item.is_done;
            item.is_done = next;

            try {
                await request('PUT', `/api/weekly-task-items/${item.id}`, { is_done: next }, { idempotencyKey: uuid() });
            } catch (error) {
                item.is_done = !next;
                this.error = error.message;
            }
        },

        async addItem(list) {
            const title = (this.newItem[list.id] ?? '').trim();

            if (title === '') {
                return;
            }

            const categoryId = this.newItemCategory[list.id] ?? '';

            try {
                const payload = await request('POST', `/api/weekly-tasks/${list.id}/items`, {
                    title,
                    weekly_task_category_id: categoryId === '' ? null : categoryId,
                }, { idempotencyKey: uuid() });

                list.items = [...list.items, payload.data];
                this.newItem[list.id] = '';
            } catch (error) {
                this.error = error.message;
            }
        },

        /** Re-file a task without retyping it. */
        async moveItem(list, item, categoryId) {
            const previous = { id: item.weekly_task_category_id, category: item.category };
            const chosen = this.categories.find((entry) => entry.id === Number(categoryId)) ?? null;

            item.weekly_task_category_id = chosen?.id ?? null;
            item.category = chosen;

            try {
                await request('PUT', `/api/weekly-task-items/${item.id}`, {
                    weekly_task_category_id: chosen?.id ?? null,
                }, { idempotencyKey: uuid() });
            } catch (error) {
                item.weekly_task_category_id = previous.id;
                item.category = previous.category;
                this.error = error.message;
            }
        },

        /**
         * The tasks under their category heading, in the order the categories
         * are arranged, with anything unfiled last — the same shape the WhatsApp
         * message takes, so the panel and the message never disagree.
         */
        groupsFor(list) {
            const items = list.items ?? [];

            if (items.every((item) => !item.category)) {
                return items.length ? [{ key: 'none', name: '', color: 'zinc', items }] : [];
            }

            const order = (item) => (item.category ? item.category.sort_order : Number.MAX_SAFE_INTEGER);
            const groups = new Map();

            [...items]
                .sort((a, b) => order(a) - order(b) || (a.category?.id ?? 0) - (b.category?.id ?? 0) || a.sort_order - b.sort_order)
                .forEach((item) => {
                    const key = item.category?.id ?? 'none';

                    if (!groups.has(key)) {
                        groups.set(key, {
                            key,
                            name: item.category?.name ?? 'أخرى',
                            color: item.category?.color ?? 'zinc',
                            items: [],
                        });
                    }

                    groups.get(key).items.push(item);
                });

            return [...groups.values()];
        },

        async removeItem(list, item) {
            try {
                await request('DELETE', `/api/weekly-task-items/${item.id}`, null, { idempotencyKey: uuid() });
                list.items = list.items.filter((entry) => entry.id !== item.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        progress(list) {
            const total = list.items?.length ?? 0;

            if (total === 0) {
                return { done: 0, total: 0, percent: 0 };
            }

            const done = list.items.filter((item) => item.is_done).length;

            return { done, total, percent: Math.round((done / total) * 100) };
        },

        get overall() {
            const items = this.lists.flatMap((list) => list.items ?? []);
            const done = items.filter((item) => item.is_done).length;

            return { done, total: items.length, percent: items.length === 0 ? 0 : Math.round((done / items.length) * 100) };
        },

        // ---- employees ------------------------------------------------------

        openEmployeeCreate() {
            this.employeeForm = { id: null, name: '', phone: '', role: '', enrolled_on: '', is_active: true };
            this.employeeErrors = {};
        },

        editEmployee(employee) {
            this.employeeForm = { ...employee };
            this.employeeErrors = {};
        },

        async saveEmployee() {
            this.employeeErrors = {};
            const isUpdate = Boolean(this.employeeForm.id);
            const body = { ...this.employeeForm };
            delete body.id;

            try {
                const payload = isUpdate
                    ? await request('PUT', `/api/employees/${this.employeeForm.id}`, body, { idempotencyKey: uuid() })
                    : await request('POST', '/api/employees', body, { idempotencyKey: uuid() });

                const saved = payload.data;
                this.employees = isUpdate
                    ? this.employees.map((item) => (item.id === saved.id ? saved : item))
                    : [...this.employees, saved];

                this.openEmployeeCreate();
            } catch (error) {
                this.employeeErrors = error instanceof ApiError ? error.errors : {};
            }
        },

        async removeEmployee(employee) {
            if (!confirm(`حذف «${employee.name}» وكل قوائمه الأسبوعية؟`)) {
                return;
            }

            try {
                await request('DELETE', `/api/employees/${employee.id}`, null, { idempotencyKey: uuid() });
                this.employees = this.employees.filter((item) => item.id !== employee.id);
                await this.loadWeek();
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- templates ------------------------------------------------------

        /** One task per line, so a whole week goes in with a single paste. */
        async addTemplate() {
            const title = this.templateForm.title.trim();

            if (title === '') {
                return;
            }

            this.templateErrors = {};

            try {
                const payload = await request('POST', '/api/weekly-task-templates', {
                    title,
                    employee_id: this.templateForm.employee_id === '' ? null : this.templateForm.employee_id,
                    weekly_task_category_id: this.templateForm.weekly_task_category_id === ''
                        ? null
                        : this.templateForm.weekly_task_category_id,
                }, { idempotencyKey: uuid() });

                this.templates = [...this.templates, ...payload.data];
                this.templateForm.title = '';
                await this.loadCategories();
            } catch (error) {
                this.templateErrors = error instanceof ApiError ? error.errors : {};
            }
        },

        get pendingTemplateCount() {
            return this.templateForm.title
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter((line) => line !== '')
                .length;
        },

        /** Templates under their heading, so the modal reads like the week does. */
        get groupedTemplates() {
            const groups = new Map();

            this.templates.forEach((template) => {
                const key = template.weekly_task_category_id ?? 'none';

                if (!groups.has(key)) {
                    const category = this.categories.find((entry) => entry.id === template.weekly_task_category_id);

                    groups.set(key, {
                        key,
                        name: category?.name ?? (template.weekly_task_category_id ? '—' : 'بلا تصنيف'),
                        color: category?.color ?? 'zinc',
                        sort: category?.sort_order ?? Number.MAX_SAFE_INTEGER,
                        items: [],
                    });
                }

                groups.get(key).items.push(template);
            });

            return [...groups.values()].sort((a, b) => a.sort - b.sort);
        },

        async removeTemplate(template) {
            try {
                await request('DELETE', `/api/weekly-task-templates/${template.id}`, null, { idempotencyKey: uuid() });
                this.templates = this.templates.filter((item) => item.id !== template.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- categories -----------------------------------------------------

        openCategoryCreate() {
            this.categoryForm = { id: null, name: '', color: 'emerald' };
            this.categoryErrors = {};
        },

        editCategory(category) {
            this.categoryForm = { id: category.id, name: category.name, color: category.color };
            this.categoryErrors = {};
        },

        async saveCategory() {
            const name = this.categoryForm.name.trim();

            if (name === '') {
                return;
            }

            this.categoryErrors = {};
            const isUpdate = Boolean(this.categoryForm.id);

            try {
                const payload = isUpdate
                    ? await request('PUT', `/api/weekly-task-categories/${this.categoryForm.id}`, {
                        name,
                        color: this.categoryForm.color,
                    }, { idempotencyKey: uuid() })
                    : await request('POST', '/api/weekly-task-categories', {
                        name,
                        color: this.categoryForm.color,
                    }, { idempotencyKey: uuid() });

                const saved = payload.data;
                this.categories = isUpdate
                    ? this.categories.map((entry) => (entry.id === saved.id ? saved : entry))
                    : [...this.categories, saved];

                this.openCategoryCreate();
                await Promise.all([this.loadTemplates(), this.loadWeek()]);
            } catch (error) {
                this.categoryErrors = error instanceof ApiError ? error.errors : {};
            }
        },

        /** The tasks survive; they simply lose their heading. */
        async removeCategory(category) {
            if (!confirm(`حذف تصنيف «${category.name}»؟ المهام تبقى كما هي بلا تصنيف.`)) {
                return;
            }

            try {
                await request('DELETE', `/api/weekly-task-categories/${category.id}`, null, { idempotencyKey: uuid() });
                this.categories = this.categories.filter((entry) => entry.id !== category.id);
                await Promise.all([this.loadTemplates(), this.loadWeek()]);
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- reporting ------------------------------------------------------

        groupSearch: '',
        candidates: [],
        resolving: false,

        capturing: false,
        captured: [],

        /**
         * Groups are picked up from messages passing through them, because
         * reading the chat list is broken on this WhatsApp Web build.
         */
        async captureGroups() {
            this.capturing = true;
            this.groupsError = '';

            try {
                const payload = await request('GET', '/api/whatsapp/seen-groups');
                this.captured = payload?.groups ?? [];

                if (!payload?.ok) {
                    this.groupsError = payload?.error ?? 'تعذر قراءة المجموعات الملتقطة.';
                } else if (this.captured.length === 0) {
                    this.groupsError = 'لم تُلتقط أي مجموعة بعد. أرسل رسالة في المجموعة المطلوبة ثم اضغط «التقاط» مجدداً.';
                }
            } catch (error) {
                this.groupsError = error.message;
            } finally {
                this.capturing = false;
            }
        },

        /** Prove the id by making a message land in the group. */
        async testGroup() {
            this.busy = true;
            this.groupsError = '';
            this.notice = '';

            try {
                await request('POST', '/api/whatsapp/test-group', {
                    group_id: this.settings.whatsapp_group_id,
                }, { idempotencyKey: uuid() });

                this.notice = 'وصلت رسالة تجريبية إلى «' + (this.settings.whatsapp_group_name ?? 'المجموعة') + '». افحص واتساب للتأكد.';
            } catch (error) {
                this.groupsError = error.message;
            } finally {
                this.busy = false;
            }
        },

        seenAgo(timestamp) {
            const minutes = Math.round((Date.now() - timestamp) / 60000);

            if (minutes < 1) {
                return 'الآن';
            }

            return minutes < 60 ? `قبل ${minutes} دقيقة` : `قبل ${Math.round(minutes / 60)} ساعة`;
        },

        /**
         * A pasted group id is taken at its word: it is what WhatsApp addresses,
         * and reading it out of the page beats every lookup this build breaks.
         */
        adoptTypedValue() {
            const typed = this.groupSearch.trim();
            const match = typed.match(/(\d{5,}(?:-\d+)?@g\.us)/);

            if (!match) {
                return false;
            }

            this.settings.whatsapp_group_id = match[1];
            this.settings.whatsapp_group_name = typed === match[1] ? match[1] : typed;
            this.candidates = [];
            this.captured = [];
            this.groupsError = '';

            return true;
        },

        /** Ask the gateway which group carries this name, and adopt its id. */
        async resolveGroup() {
            const name = this.groupSearch.trim();

            if (name === '') {
                this.groupsError = 'اكتب اسم المجموعة أو الصق معرّفها.';

                return;
            }

            // Pasted an id rather than a name: nothing to look up.
            if (this.adoptTypedValue()) {
                return;
            }

            this.resolving = true;
            this.groupsError = '';
            this.candidates = [];

            try {
                const payload = await request('POST', '/api/whatsapp/resolve-group', { name }, { idempotencyKey: uuid() });
                const { matched, candidates } = payload.data;

                if (matched) {
                    this.chooseGroup(matched);
                    this.groupSearch = matched.name;
                } else {
                    this.candidates = candidates;
                    this.groupsError = 'لم يطابق الاسم مجموعة واحدة بالضبط. اختر من الأقرب:';
                }
            } catch (error) {
                this.groupsError = error.message;
            } finally {
                this.resolving = false;
            }
        },

        chooseGroup(group) {
            this.settings.whatsapp_group_id = group.id;
            // A captured group may arrive without a name; the typed label stands in.
            this.settings.whatsapp_group_name = group.name || this.groupSearch.trim() || group.id;
            this.groupSearch = this.settings.whatsapp_group_name;
            this.candidates = [];
            this.captured = [];
            this.groupsError = '';
        },

        async saveSettings() {
            this.busy = true;

            try {
                const payload = await request('PUT', '/api/weekly-report-settings', {
                    whatsapp_group_id: this.settings.whatsapp_group_id,
                    whatsapp_group_name: this.settings.whatsapp_group_name,
                    weekly_reports_enabled: this.settings.weekly_reports_enabled,
                }, { idempotencyKey: uuid() });

                this.settings = payload.data;
                this.showSettings = false;
                this.notice = 'حُفظت إعدادات التقارير.';
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },

        async showPreview(kind) {
            this.preview = { open: true, kind, message: '', busy: true };

            try {
                const payload = await request('GET', `/api/weekly-tasks/preview?kind=${kind}`);
                this.preview.message = payload?.data?.message ?? 'لا توجد مهام لهذا الأسبوع.';
            } catch (error) {
                this.preview.message = error.message;
            } finally {
                this.preview.busy = false;
            }
        },

        /** Send now rather than waiting for Saturday or Thursday. */
        async sendNow(kind) {
            if (!confirm(kind === 'opening' ? 'إرسال مهام الأسبوع إلى المجموعة الآن؟' : 'إرسال ملخص الإنجاز إلى المجموعة الآن؟')) {
                return;
            }

            this.busy = true;
            this.error = '';
            this.notice = '';

            try {
                await request('POST', '/api/weekly-tasks/send', { kind }, { idempotencyKey: uuid() });
                this.notice = 'أُرسلت الرسالة إلى ' + (this.settings.whatsapp_group_name ?? 'المجموعة') + '.';
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },
    };
}
