import { ApiError, request } from '../api';
import { uuid } from '../ids';

export default function weeklyTasksPage() {
    return {
        employees: [],
        templates: [],
        lists: [],
        settings: { whatsapp_group_id: null, whatsapp_group_name: null, weekly_reports_enabled: false, is_ready: false },
        groupsError: '',

        loading: true,
        busy: false,
        error: '',
        notice: '',

        employeeForm: { id: null, name: '', phone: '', role: '', enrolled_on: '', is_active: true },
        employeeErrors: {},
        showEmployees: false,

        templateForm: { title: '', employee_id: '' },
        templateErrors: {},
        showTemplates: false,

        showSettings: false,
        preview: { open: false, kind: 'opening', message: '', busy: false },

        newItem: {},

        async init() {
            await Promise.all([this.loadEmployees(), this.loadTemplates(), this.loadWeek(), this.loadSettings()]);
            this.loading = false;
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

        async loadWeek() {
            try {
                this.lists = (await request('GET', '/api/weekly-tasks'))?.data ?? [];
            } catch (error) {
                this.error = error.message;
            }
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
                const { created, skipped } = payload.data;
                this.notice = `أُنشئت ${created} قائمة، وتُركت ${skipped} قائمة موجودة.`;
                await this.loadWeek();
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

            try {
                const payload = await request('POST', `/api/weekly-tasks/${list.id}/items`, { title }, { idempotencyKey: uuid() });
                list.items = [...list.items, payload.data];
                this.newItem[list.id] = '';
            } catch (error) {
                this.error = error.message;
            }
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
                }, { idempotencyKey: uuid() });

                this.templates = [...this.templates, payload.data];
                this.templateForm.title = '';
            } catch (error) {
                this.templateErrors = error instanceof ApiError ? error.errors : {};
            }
        },

        async removeTemplate(template) {
            try {
                await request('DELETE', `/api/weekly-task-templates/${template.id}`, null, { idempotencyKey: uuid() });
                this.templates = this.templates.filter((item) => item.id !== template.id);
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
                await request('POST', '/api/whatsapp/test-group', {}, { idempotencyKey: uuid() });
                this.notice = 'وصلت رسالة تجريبية إلى المجموعة. المعرّف صحيح.';
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
