import { ApiError, request } from '../api';
import { uuid } from '../ids';

const BASE = '/api/accounts';

export const CATEGORY_COLORS = ['emerald', 'sky', 'violet', 'amber', 'rose', 'teal', 'indigo', 'zinc'];

/**
 * Tailwind needs to see every class it must emit, so the colour variants are
 * written out in full rather than assembled from a string at runtime.
 */
export const COLOR_CLASSES = {
    emerald: { chip: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200', dot: 'bg-emerald-500', bar: 'bg-emerald-500', ring: 'ring-emerald-500' },
    sky: { chip: 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200', dot: 'bg-sky-500', bar: 'bg-sky-500', ring: 'ring-sky-500' },
    violet: { chip: 'bg-violet-100 text-violet-800 dark:bg-violet-900/50 dark:text-violet-200', dot: 'bg-violet-500', bar: 'bg-violet-500', ring: 'ring-violet-500' },
    amber: { chip: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200', dot: 'bg-amber-500', bar: 'bg-amber-500', ring: 'ring-amber-500' },
    rose: { chip: 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200', dot: 'bg-rose-500', bar: 'bg-rose-500', ring: 'ring-rose-500' },
    teal: { chip: 'bg-teal-100 text-teal-800 dark:bg-teal-900/50 dark:text-teal-200', dot: 'bg-teal-500', bar: 'bg-teal-500', ring: 'ring-teal-500' },
    indigo: { chip: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200', dot: 'bg-indigo-500', bar: 'bg-indigo-500', ring: 'ring-indigo-500' },
    zinc: { chip: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300', dot: 'bg-zinc-400', bar: 'bg-zinc-400', ring: 'ring-zinc-400' },
};

export default function accountsPage() {
    return {
        accounts: [],
        categories: [],
        templates: [],
        loading: true,
        saving: false,
        error: '',

        search: '',
        activeCategory: 'all',
        collapsed: {},

        colors: CATEGORY_COLORS,

        form: { id: null, account_category_id: '', name: '', identifier: '', password: '' },
        formErrors: {},
        showForm: false,

        categoryForm: { id: null, name: '', color: 'emerald' },
        categoryErrors: {},
        showCategories: false,

        templateForm: '',
        showTemplates: false,
        newTask: {},

        pinIsSet: false,
        pinPrompt: { open: false, accountId: null, value: '', error: '', busy: false },
        pinSetup: { open: false, pin: '', pin_confirmation: '', current_password: '', error: '', busy: false },

        // Revealed passwords live here only, never in the store or localStorage.
        revealed: {},
        revealTimers: {},

        async init() {
            await Promise.all([this.loadAccounts(), this.loadCategories(), this.loadTemplates(), this.loadPinState()]);
            this.loading = false;
        },

        async loadAccounts() {
            try {
                const payload = await request('GET', BASE);
                this.accounts = payload?.data ?? [];
            } catch (error) {
                this.error = error.message;
            }
        },

        async loadCategories() {
            try {
                const payload = await request('GET', '/api/account-categories');
                this.categories = payload?.data ?? [];
            } catch {
                this.categories = [];
            }
        },

        async loadTemplates() {
            try {
                const payload = await request('GET', '/api/task-templates');
                this.templates = payload?.data ?? [];
            } catch {
                this.templates = [];
            }
        },

        async loadPinState() {
            try {
                const payload = await request('GET', '/api/reveal-pin');
                this.pinIsSet = payload?.data?.is_set ?? false;
            } catch {
                this.pinIsSet = false;
            }
        },

        // ---- presentation ---------------------------------------------------

        classesFor(color) {
            return COLOR_CLASSES[color] ?? COLOR_CLASSES.zinc;
        },

        categoryOf(account) {
            return account.category ?? null;
        },

        get visible() {
            const term = this.search.trim();

            return this.accounts.filter((account) => {
                const inCategory = this.activeCategory === 'all'
                    || (this.activeCategory === 'none' && !account.account_category_id)
                    || account.account_category_id === this.activeCategory;

                const matches = term === ''
                    || `${account.name} ${account.identifier}`.includes(term);

                return inCategory && matches;
            });
        },

        countFor(categoryId) {
            if (categoryId === 'all') {
                return this.accounts.length;
            }

            if (categoryId === 'none') {
                return this.accounts.filter((account) => !account.account_category_id).length;
            }

            return this.accounts.filter((account) => account.account_category_id === categoryId).length;
        },

        /** Tasks finished across everything currently on screen. */
        get overallProgress() {
            const tasks = this.visible.flatMap((account) => account.tasks ?? []);
            const done = tasks.filter((task) => task.is_done).length;

            return {
                done,
                total: tasks.length,
                percent: tasks.length === 0 ? 0 : Math.round((done / tasks.length) * 100),
            };
        },

        progress(account) {
            const total = account.tasks?.length ?? 0;

            if (total === 0) {
                return { done: 0, total: 0, percent: 0 };
            }

            const done = account.tasks.filter((task) => task.is_done).length;

            return { done, total, percent: Math.round((done / total) * 100) };
        },

        toggleCollapse(accountId) {
            this.collapsed[accountId] = !this.collapsed[accountId];
        },

        // ---- accounts -------------------------------------------------------

        openCreate() {
            this.form = {
                id: null,
                account_category_id: this.activeCategory !== 'all' && this.activeCategory !== 'none' ? this.activeCategory : '',
                name: '',
                identifier: '',
                password: '',
            };
            this.formErrors = {};
            this.showForm = true;
        },

        openEdit(account) {
            this.form = {
                id: account.id,
                account_category_id: account.account_category_id ?? '',
                name: account.name,
                identifier: account.identifier,
                password: '',
            };
            this.formErrors = {};
            this.showForm = true;
        },

        async saveAccount() {
            this.saving = true;
            this.formErrors = {};

            const isUpdate = this.form.id !== null;
            const body = {
                name: this.form.name,
                identifier: this.form.identifier,
                account_category_id: this.form.account_category_id === '' ? null : this.form.account_category_id,
            };

            // An untouched password field on edit must not wipe the stored one.
            if (this.form.password !== '' || !isUpdate) {
                body.password = this.form.password;
            }

            try {
                const payload = isUpdate
                    ? await request('PUT', `${BASE}/${this.form.id}`, body, { idempotencyKey: uuid() })
                    : await request('POST', BASE, body, { idempotencyKey: uuid() });

                const saved = payload.data;

                this.accounts = isUpdate
                    ? this.accounts.map((item) => (item.id === saved.id ? saved : item))
                    : [...this.accounts, saved];

                this.showForm = false;
                this.form.password = '';
                await this.loadCategories();
            } catch (error) {
                this.formErrors = error instanceof ApiError ? error.errors : {};
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },

        async deleteAccount(account) {
            if (!confirm(`حذف «${account.name}» وكل مهامه؟`)) {
                return;
            }

            try {
                await request('DELETE', `${BASE}/${account.id}`, null, { idempotencyKey: uuid() });
                this.accounts = this.accounts.filter((item) => item.id !== account.id);
                this.hide(account.id);
                await this.loadCategories();
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
            this.categoryErrors = {};

            const isUpdate = this.categoryForm.id !== null;
            const body = { name: this.categoryForm.name, color: this.categoryForm.color };

            try {
                const payload = isUpdate
                    ? await request('PUT', `/api/account-categories/${this.categoryForm.id}`, body, { idempotencyKey: uuid() })
                    : await request('POST', '/api/account-categories', body, { idempotencyKey: uuid() });

                const saved = payload.data;

                this.categories = isUpdate
                    ? this.categories.map((item) => (item.id === saved.id ? saved : item))
                    : [...this.categories, saved];

                this.openCategoryCreate();

                if (isUpdate) {
                    await this.loadAccounts();
                }
            } catch (error) {
                this.categoryErrors = error instanceof ApiError ? error.errors : {};
            }
        },

        async deleteCategory(category) {
            if (!confirm(`حذف تصنيف «${category.name}»؟ الحسابات بداخله ستبقى بلا تصنيف.`)) {
                return;
            }

            try {
                await request('DELETE', `/api/account-categories/${category.id}`, null, { idempotencyKey: uuid() });
                this.categories = this.categories.filter((item) => item.id !== category.id);

                if (this.activeCategory === category.id) {
                    this.activeCategory = 'all';
                }

                await this.loadAccounts();
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- reveal ---------------------------------------------------------

        askForPin(account) {
            if (!this.pinIsSet) {
                this.pinSetup.open = true;

                return;
            }

            this.pinPrompt = { open: true, accountId: account.id, value: '', error: '', busy: false };
        },

        async submitPin() {
            this.pinPrompt.busy = true;
            this.pinPrompt.error = '';

            try {
                const payload = await request('POST', `${BASE}/${this.pinPrompt.accountId}/reveal`, {
                    pin: this.pinPrompt.value,
                });

                this.revealed[this.pinPrompt.accountId] = payload.data.secret;
                this.scheduleHide(this.pinPrompt.accountId);
                this.pinPrompt.open = false;
            } catch (error) {
                this.pinPrompt.error = error.errors?.pin?.[0] ?? error.message;
            } finally {
                this.pinPrompt.busy = false;
            }
        },

        /** Revealed passwords clear themselves so they do not linger on screen. */
        scheduleHide(accountId) {
            clearTimeout(this.revealTimers[accountId]);
            this.revealTimers[accountId] = setTimeout(() => this.hide(accountId), 60000);
        },

        hide(accountId) {
            clearTimeout(this.revealTimers[accountId]);
            delete this.revealTimers[accountId];
            delete this.revealed[accountId];
        },

        async copy(accountId) {
            const value = this.revealed[accountId];

            if (!value) {
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
                this.copied = accountId;
                setTimeout(() => {
                    this.copied = null;
                }, 1500);
            } catch {
                this.error = 'تعذر النسخ إلى الحافظة';
            }
        },

        copied: null,

        async saveRevealPin() {
            this.pinSetup.busy = true;
            this.pinSetup.error = '';

            try {
                await request('PUT', '/api/reveal-pin', {
                    pin: this.pinSetup.pin,
                    pin_confirmation: this.pinSetup.pin_confirmation,
                    current_password: this.pinSetup.current_password,
                });

                this.pinIsSet = true;
                this.pinSetup = { open: false, pin: '', pin_confirmation: '', current_password: '', error: '', busy: false };
            } catch (error) {
                this.pinSetup.error = error.errors?.pin?.[0] ?? error.errors?.current_password?.[0] ?? error.message;
            } finally {
                this.pinSetup.busy = false;
            }
        },

        // ---- tasks ----------------------------------------------------------

        async toggleTask(account, task) {
            const next = !task.is_done;
            task.is_done = next;

            try {
                await request('PUT', `/api/account-tasks/${task.id}`, { is_done: next }, { idempotencyKey: uuid() });
            } catch (error) {
                task.is_done = !next;
                this.error = error.message;
            }
        },

        async addTask(account) {
            const title = (this.newTask[account.id] ?? '').trim();

            if (title === '') {
                return;
            }

            try {
                const payload = await request('POST', `${BASE}/${account.id}/tasks`, { title }, { idempotencyKey: uuid() });
                account.tasks = [...account.tasks, payload.data];
                this.newTask[account.id] = '';
            } catch (error) {
                this.error = error.message;
            }
        },

        async deleteTask(account, task) {
            try {
                await request('DELETE', `/api/account-tasks/${task.id}`, null, { idempotencyKey: uuid() });
                account.tasks = account.tasks.filter((item) => item.id !== task.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        async applyTemplates(account) {
            try {
                const payload = await request('POST', `${BASE}/${account.id}/apply-templates`, {}, { idempotencyKey: uuid() });
                this.accounts = this.accounts.map((item) => (item.id === payload.data.id ? payload.data : item));
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- task templates -------------------------------------------------

        async addTemplate() {
            const title = this.templateForm.trim();

            if (title === '') {
                return;
            }

            try {
                const payload = await request('POST', '/api/task-templates', { title }, { idempotencyKey: uuid() });
                this.templates = [...this.templates, payload.data];
                this.templateForm = '';
            } catch (error) {
                this.error = error.message;
            }
        },

        async deleteTemplate(template) {
            try {
                await request('DELETE', `/api/task-templates/${template.id}`, null, { idempotencyKey: uuid() });
                this.templates = this.templates.filter((item) => item.id !== template.id);
            } catch (error) {
                this.error = error.message;
            }
        },
    };
}
